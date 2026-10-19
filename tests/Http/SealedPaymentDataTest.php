<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentAttempt;
use App\Models\ProviderRef;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\PaymentVault;
use Naluz\Database\QueryException;
use Naluz\Security\DecryptException;
use Naluz\Tests\TestCase;

/** What identifies a payment at the provider is encrypted in the database, and plain again inside the app. */
final class SealedPaymentDataTest extends TestCase
{
    private User $owner;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->page = $this->makePage($this->owner);
    }

    private function order(array $over = []): Order
    {
        static $n = 0;
        $order = new Order();
        $order->forceFill($over + [
            'reference' => 'LM-TEST-' . str_pad((string) ++$n, 4, '0', STR_PAD_LEFT), 'user_id' => $this->owner->id, 'page_id' => $this->page->id,
            'plan' => 'sweet', 'period' => 'month', 'amount' => 20000, 'currency' => 'PHP', 'status' => 'pending', 'provider' => 'paymongo',
        ])->save();
        return $order;
    }

    /** @return array<string,mixed> the row as the database holds it */
    private function raw(string $table, int $id): array
    {
        return (array) $this->db()->table($table)->where('id', '=', $id)->first();
    }

    public function testAnOrdersProviderReferenceAndCheckoutPageAreEncryptedInTheDatabase(): void
    {
        $order = $this->order(['provider_ref' => 'pi_secret_123', 'checkout_url' => 'https://checkout.example.com/pay/abc']);

        $raw = $this->raw('orders', $order->id);
        $this->assertNotSame('pi_secret_123', $raw['provider_ref']);
        $this->assertStringNotContainsString('secret', (string) $raw['provider_ref']);
        $this->assertStringNotContainsString('checkout.example.com', (string) $raw['checkout_url']);
        $this->assertSame('pi_secret_123', PaymentVault::open($raw['provider_ref']), 'it is the app key that opens it');
        $this->assertSame(PaymentVault::index('pi_secret_123'), $raw['provider_ref_idx']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $raw['provider_ref_idx']);
        $this->assertNotSame(hash('sha256', 'pi_secret_123'), $raw['provider_ref_idx'], 'keyed: a plain hash of a guess does not match');

        // inside the app it is plain again
        $loaded = Order::query()->withoutCache()->find($order->id);
        $this->assertSame(['pi_secret_123', 'https://checkout.example.com/pay/abc'], [$loaded->provider_ref, $loaded->checkout_url]);
        $this->assertSame($order->id, Order::withProviderRef('pi_secret_123')->id, 'and found by its blind index');
        $this->assertNull(Order::withProviderRef('pi_other'));
    }

    public function testTheSameValueIsSealedDifferentlyEachTimeButIndexedTheSame(): void
    {
        $a = $this->order(['provider_ref' => 'pi_same']);
        $b = $this->order();
        $this->assertSame(1, $this->db()->table('orders')->where('provider_ref_idx', '=', PaymentVault::index('pi_same'))->count());
        $this->assertNotSame(PaymentVault::seal('x'), PaymentVault::seal('x'), 'a fresh nonce every time');
        $this->assertNull($this->raw('orders', $b->id)['provider_ref']);
        $this->assertNull($this->raw('orders', $b->id)['provider_ref_idx']);
        $this->assertNotNull($a);
    }

    public function testAProviderIdCannotBeOnTwoOrders(): void
    {
        $this->order(['provider_ref' => 'pi_dup']);
        $this->expectException(QueryException::class);
        $this->order(['provider_ref' => 'pi_dup']); // the blind index is unique, as the plain column was
    }

    public function testAttemptsSubscriptionsAndProviderIdsAreSealedToo(): void
    {
        $order = $this->order();
        $attempt = new PaymentAttempt();
        $attempt->forceFill(['order_id' => $order->id, 'provider' => 'paymongo', 'provider_ref' => 'pi_att_1', 'method' => 'gcash', 'billing' => 'one_time',
            'redirect_url' => 'https://pay.example.com/approve/xyz', 'subscription_ref' => 'sub_att_1'])->save();
        $sub = new Subscription();
        $sub->forceFill(['user_id' => $this->owner->id, 'page_id' => $this->page->id, 'plan' => 'sweet', 'period' => 'month', 'amount' => 20000, 'currency' => 'PHP',
            'method' => 'card', 'provider' => 'paymongo', 'mode' => 'auto', 'provider_ref' => 'sub_secret', 'last_invoice_ref' => 'inv_secret'])->save();
        ProviderRef::remember('paymongo', 'customer', 'user:1', 'cus_secret');

        foreach ([['payment_attempts', $attempt->id, ['provider_ref', 'redirect_url', 'subscription_ref']], ['subscriptions', $sub->id, ['provider_ref', 'last_invoice_ref']],
            ['provider_refs', (int) $this->db()->table('provider_refs')->first()['id'], ['ref']]] as [$table, $id, $columns]) {
            $raw = $this->raw($table, $id);
            foreach ($columns as $column) {
                $this->assertNotNull($raw[$column], "$table.$column");
                $this->assertDoesNotMatchRegularExpression('/pi_att|sub_att|sub_secret|inv_secret|cus_secret|pay\.example/', (string) $raw[$column], "$table.$column is not plain");
                $this->assertNotNull(PaymentVault::open((string) $raw[$column]), "$table.$column opens with the key");
            }
        }
        $loaded = PaymentAttempt::withProviderRef('paymongo', 'pi_att_1');
        $this->assertSame([$order->id, 'https://pay.example.com/approve/xyz', 'sub_att_1'], [$loaded->order_id, $loaded->redirect_url, $loaded->subscription_ref]);
        $this->assertNull(PaymentAttempt::withProviderRef('xendit', 'pi_att_1'), 'per provider');
        $loadedSub = Subscription::query()->withoutCache()->find($sub->id);
        $this->assertSame(['sub_secret', 'inv_secret'], [$loadedSub->provider_ref, $loadedSub->last_invoice_ref]);
        $this->assertSame('cus_secret', ProviderRef::lookup('paymongo', 'customer', 'user:1'));
    }

    public function testMarkingAnOrderPaidSealsTheReferenceItWritesWithRawSql(): void
    {
        $order = $this->order();
        $this->assertTrue($this->app->make(Billing::class)->markPaid($order, 'paymongo', 'pay_ref_777'));

        $raw = $this->raw('orders', $order->id);
        $this->assertSame('paid', $raw['status']);
        $this->assertStringNotContainsString('pay_ref_777', (string) $raw['provider_ref']);
        $this->assertSame(PaymentVault::index('pay_ref_777'), $raw['provider_ref_idx']);
        $this->assertSame('pay_ref_777', Order::query()->withoutCache()->find($order->id)->provider_ref);
        $this->assertSame('pay_ref_777', $order->provider_ref, 'the order handed back reads it plain too');
    }

    public function testTamperedOrForeignDataIsRefusedNotReadWrong(): void
    {
        $order = $this->order(['provider_ref' => 'pi_tamper']);
        $raw = (string) $this->raw('orders', $order->id)['provider_ref'];
        $broken = substr($raw, 0, -2) . (substr($raw, -2) === 'AA' ? 'BB' : 'AA');
        $this->db()->table('orders')->where('id', '=', $order->id)->update(['provider_ref' => $broken]);

        $this->expectException(DecryptException::class);
        Order::query()->withoutCache()->find($order->id)->provider_ref;
    }

    public function testValuesThatWereStoredPlainAreSealedOnceAndTheRunCanBeRepeated(): void
    {
        $order = $this->order();
        $this->db()->table('orders')->where('id', '=', $order->id)->update(['provider_ref' => 'old_plain_ref', 'provider_ref_idx' => null, 'checkout_url' => 'https://old.example.com/c']);
        $other = $this->order(['provider_ref' => 'pi_already_sealed']);
        $before = $this->raw('orders', $other->id)['provider_ref'];

        $this->assertSame(2, PaymentVault::sealAll($this->db()));
        $raw = $this->raw('orders', $order->id);
        $this->assertStringNotContainsString('old_plain_ref', (string) $raw['provider_ref']);
        $this->assertSame(PaymentVault::index('old_plain_ref'), $raw['provider_ref_idx']);
        $this->assertSame(['old_plain_ref', 'https://old.example.com/c'], [Order::query()->withoutCache()->find($order->id)->provider_ref, Order::query()->withoutCache()->find($order->id)->checkout_url]);
        $this->assertSame($before, $this->raw('orders', $other->id)['provider_ref'], 'a sealed value is left as it is');
        $this->assertSame(0, PaymentVault::sealAll($this->db()), 'nothing left to seal');

        $this->assertGreaterThan(0, PaymentVault::sealAll($this->db(), true), 'a key rotation seals everything again');
        $this->assertSame('pi_already_sealed', Order::query()->withoutCache()->find($other->id)->provider_ref);
    }

    public function testASealedValueNoKeyCanReadStopsTheRunInsteadOfBeingSealedAgain(): void
    {
        $order = $this->order();
        $foreign = rtrim(strtr(base64_encode(random_bytes(80)), '+/', '-_'), '=');
        $this->db()->table('orders')->where('id', '=', $order->id)->update(['provider_ref' => $foreign]);
        $this->expectException(\RuntimeException::class);
        PaymentVault::sealAll($this->db());
    }

    public function testThePaymentsPageStillFindsAnOrderByItsWholeProviderId(): void
    {
        $admin = new User();
        $admin->forceFill(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'correct horse', 'is_admin' => true])->save();
        $order = $this->order(['provider_ref' => 'pi_find_me_123']);
        $this->signIn($admin);

        $this->assertStringContainsString($order->reference, (string) $this->get('/admin/payments?q=pi_find_me_123')->getBody());
        $html = (string) $this->get('/admin/payments?q=pi_find')->getBody();
        $this->assertStringContainsString('No payments match', $html, 'the ids are encrypted: a part of one finds nothing');
        $this->assertStringContainsString('pi_find_me_123', (string) $this->get('/admin/payments')->getBody(), 'but the page shows it, decrypted');
    }
}
