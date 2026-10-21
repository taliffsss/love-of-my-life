<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Photo;
use App\Models\User;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

final class AccountTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->signIn($this->owner);
    }

    private function avatar(?string $bytes, ?int $size = null): ResponseInterface
    {
        $request = new ServerRequest('POST', '/account/avatar', ['X-CSRF-TOKEN' => $this->csrf('/')]);
        if ($bytes !== null) {
            $request = $request->withUploadedFiles(['avatar' => new UploadedFile(Stream::create($bytes), $size ?? strlen($bytes), UPLOAD_ERR_OK, 'me.png', 'image/png')]);
        }
        return $this->send($request);
    }

    public function testEveryoneSeesTheirProfileAndAdminsToo(): void
    {
        $html = (string) $this->get('/account')->getBody();
        $this->assertStringContainsString('value="Owner"', $html);
        $this->assertStringContainsString('value="owner@example.com"', $html);
        foreach (['name="phone"', 'name="address_line1"', 'name="city"', 'name="region"', 'name="postal_code"', 'name="country"', 'name="avatar"', 'name="current_password"'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
        $this->assertStringContainsString('href="/account/billing"', $html);

        $admin = $this->makeUser('boss@example.com');
        $admin->forceFill(['is_admin' => true])->save();
        $this->form('POST', '/logout', ['_token' => $this->csrf('/')]);
        $this->signIn($admin);
        $this->assertSame(200, $this->get('/account')->getStatusCode());
        $this->assertSame(200, $this->get('/account/billing')->getStatusCode());
        $this->assertStringContainsString('Administrator', (string) $this->get('/account')->getBody());
    }

    public function testSignedOutVisitorsAreSentToSignIn(): void
    {
        $this->form('POST', '/logout', ['_token' => $this->csrf('/')]);
        $this->assertSame('/login', $this->get('/account')->getHeaderLine('Location'));
        $this->assertSame('/login', $this->get('/account/billing')->getHeaderLine('Location'));
    }

    public function testTheProfileCanBeChanged(): void
    {
        $response = $this->submit('PUT', '/account/profile', [
            'name' => '  Mark   Anthony ', 'email' => 'owner@example.com', 'phone' => '+63 917 123 4567',
            'address_line1' => '12 Rose St', 'address_line2' => 'Unit 4', 'city' => 'Quezon City', 'region' => 'Metro Manila', 'postal_code' => '1100', 'country' => 'Philippines',
        ]);
        $this->assertSame('/account', $response->getHeaderLine('Location'));
        $user = $this->owner->fresh();
        $this->assertSame('Mark Anthony', $user->name);
        $this->assertSame('+63 917 123 4567', $user->phone);
        $this->assertSame('12 Rose St, Unit 4, Quezon City, Metro Manila 1100, Philippines', $user->addressLine());

        $this->submit('PUT', '/account/profile', ['name' => 'Mark', 'email' => 'owner@example.com', 'phone' => '', 'address_line1' => '', 'city' => '']);
        $this->assertNull($this->owner->fresh()->phone, 'an emptied field is cleared');
        $this->assertSame('', $this->owner->fresh()->addressLine());
    }

    public function testBadDetailsAreRefusedAndNothingIsSaved(): void
    {
        $this->makeUser('taken@example.com');
        $this->submit('PUT', '/account/profile', ['name' => '', 'email' => 'taken@example.com', 'phone' => 'call me', 'postal_code' => '$$$']);
        $html = (string) $this->get('/account')->getBody();
        foreach (['Enter your name', 'Another account already has this e-mail', 'Use digits only', 'letters, numbers, spaces and dashes'] as $message) {
            $this->assertStringContainsString($message, $html);
        }
        $this->assertSame('Owner', $this->owner->fresh()->name);
        $this->assertSame('owner@example.com', $this->owner->fresh()->email);
    }

    public function testWithoutConfirmationTheEmailChangesAtOnce(): void
    {
        $this->submit('PUT', '/account/profile', ['name' => 'Owner', 'email' => 'New@Example.com']);
        $this->assertSame('new@example.com', $this->owner->fresh()->email);
        $this->assertNull($this->owner->fresh()->pending_email);
    }

    public function testThePasswordNeedsTheCurrentOne(): void
    {
        $this->submit('PUT', '/account/password', ['current_password' => 'wrong', 'password' => 'a new password', 'password_confirmation' => 'a new password']);
        $this->assertStringContainsString('not your current password', (string) $this->get('/account')->getBody());

        $this->submit('PUT', '/account/password', ['current_password' => 'correct horse', 'password' => 'a new password', 'password_confirmation' => 'a new password']);
        $this->form('POST', '/logout', ['_token' => $this->csrf('/')]);
        $this->signIn($this->owner, 'a new password');
    }

    public function testASocialAccountMayAddAPasswordWithoutAnOldOne(): void
    {
        $this->owner->forceFill(['login_type' => 'google'])->save();
        $this->submit('PUT', '/account/password', ['password' => 'a new password', 'password_confirmation' => 'a new password']);
        $this->form('POST', '/logout', ['_token' => $this->csrf('/')]);
        $this->signIn($this->owner, 'a new password');
    }

    public function testTheProfilePhotoIsFiledUnderTheAccountsFolderAndOnlyItsKeyIsSaved(): void
    {
        $this->assertSame(303, $this->avatar($this->png())->getStatusCode());
        $user = $this->owner->fresh();
        $this->assertSame('spaces', $user->avatar_disk);
        $this->assertMatchesRegularExpression('#^users/' . $user->id . '/avatar/[0-9a-f]{32}\.png$#', $user->avatar_path);
        $this->assertArrayHasKey($user->avatar_path, $this->disk->files);
        $this->assertArrayNotHasKey('url', $user->getRawAttributes(), 'no URL column: the address is made from the key');
        $this->assertStringContainsString('src="https://cdn.test/' . $user->avatar_path . '"', (string) $this->get('/account')->getBody());

        $first = $user->avatar_path;
        $this->avatar($this->png());
        $second = $this->owner->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        $this->assertContains($first, $this->disk->deleted, 'the replaced file is deleted');

        $this->submit('DELETE', '/account/avatar');
        $this->assertNull($this->owner->fresh()->avatar_path);
        $this->assertContains($second, $this->disk->deleted);
    }

    public function testAProfilePhotoIsLimitedTo10MbWithAClearMessage(): void
    {
        $this->avatar($this->png(), 11 * 1024 * 1024);
        $this->assertStringContainsString('This photo is 11 MB. A profile photo can be up to 10 MB.', (string) $this->get('/account')->getBody());
        $this->assertNull($this->owner->fresh()->avatar_path);
        $this->assertSame([], $this->disk->files);

        $this->avatar($this->png(), 10 * 1024 * 1024);
        $this->assertNotNull($this->owner->fresh()->avatar_path, 'exactly 10 MB is fine');
    }

    public function testTheProfilePhotoFormIsADropzoneThatSaysTheLimit(): void
    {
        $html = (string) $this->get('/account')->getBody();
        $this->assertStringContainsString('data-dropzone', $html);
        $this->assertStringContainsString('data-max="10485760"', $html);
        $this->assertStringContainsString('up to 10 MB', $html);
        $this->assertStringContainsString('class="dropzone"', $html);
    }

    public function testOnlyImagesAreAcceptedAsAProfilePhoto(): void
    {
        $this->avatar('<?php echo 1;');
        $this->assertNull($this->owner->fresh()->avatar_path);
        $this->assertSame([], $this->disk->files);
        $this->avatar(null);
        $this->assertNull($this->owner->fresh()->avatar_path);
    }

    public function testPagePhotosAreFiledUnderTheOwnersFolderWithNoUrlInTheDatabase(): void
    {
        $page = $this->makePage($this->owner);
        $this->makePhoto($page, 'https://cdn.test/a.jpg');
        $photo = Photo::query()->where('page_id', $page->id)->first();
        $this->assertSame('users/' . $this->owner->id . '/photos/a.jpg', $photo->path);
        $this->assertSame('', (string) $photo->url);
        $this->assertSame('https://cdn.test/users/' . $this->owner->id . '/photos/a.jpg', app(\App\Services\PhotoStorage::class)->photoUrl($photo));
    }

    public function testAnImportedPhotoKeepsItsOwnUrl(): void
    {
        $page = $this->makePage($this->owner);
        $photo = new Photo();
        $photo->forceFill(['page_id' => $page->id, 'disk' => null, 'path' => null, 'url' => 'https://old.example/p.jpg', 'position' => 1])->save();
        $this->assertSame('https://old.example/p.jpg', app(\App\Services\PhotoStorage::class)->photoUrl($photo));
    }

    public function testBillingShowsSubscriptionsPaymentsAndDetails(): void
    {
        $this->owner->forceFill(['phone' => '+63 917 000 0000', 'city' => 'Cebu', 'country' => 'Philippines'])->save();
        $page = $this->makeLivePage($this->owner, ['recipient' => 'Mara', 'plan' => 'sweet']);
        $order = new Order();
        $order->forceFill([
            'reference' => Order::newReference(), 'user_id' => $this->owner->id, 'page_id' => $page->id, 'plan' => 'sweet', 'period' => 'month', 'photos' => 0, 'amount' => 20000,
            'currency' => 'PHP', 'status' => Order::PAID, 'provider' => 'manual', 'paid_at' => new \DateTimeImmutable(), 'method' => 'gcash',
        ])->save();
        $pending = new Order();
        $pending->forceFill([
            'reference' => Order::newReference(), 'user_id' => $this->owner->id, 'page_id' => null, 'plan' => Order::PHOTO_PACK, 'period' => 'once', 'photos' => 12, 'amount' => 10000,
            'currency' => 'PHP', 'status' => Order::PENDING, 'provider' => 'manual',
        ])->save();

        $html = (string) $this->get('/account/billing')->getBody();
        $this->assertStringContainsString($order->reference, $html);
        $this->assertStringContainsString('for Mara', $html);
        $this->assertStringContainsString('₱200', $html);
        $this->assertStringContainsString('12 extra photos', $html);
        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('You have paid ₱200 in total', $html);
        $this->assertStringContainsString('+63 917 000 0000', $html);
        $this->assertStringContainsString('Cebu, Philippines', $html);
        $this->assertStringContainsString('Photos on your account', $html);
    }

    public function testBillingShowsOnlyTheSignedInAccountsOrders(): void
    {
        $other = $this->makeUser('other@example.com');
        $order = new Order();
        $order->forceFill([
            'reference' => 'LM-ZZZZ-ZZZZ', 'user_id' => $other->id, 'page_id' => null, 'plan' => Order::PHOTO_PACK, 'period' => 'once', 'photos' => 12, 'amount' => 10000,
            'currency' => 'PHP', 'status' => Order::PAID, 'provider' => 'manual',
        ])->save();
        $html = (string) $this->get('/account/billing')->getBody();
        $this->assertStringNotContainsString('LM-ZZZZ-ZZZZ', $html);
        $this->assertStringContainsString('Your orders will show up here', $html);
    }

    public function testAnAdminSeesProfileDetailsInTheUserScreen(): void
    {
        $admin = $this->makeUser('boss@example.com');
        $admin->forceFill(['is_admin' => true])->save();
        $this->owner->forceFill(['phone' => '+63 917 111 2222', 'city' => 'Davao'])->save();
        $this->form('POST', '/logout', ['_token' => $this->csrf('/')]);
        $this->signIn($admin);
        $html = (string) $this->get('/admin/users/' . $this->owner->id . '/edit')->getBody();
        $this->assertStringContainsString('+63 917 111 2222', $html);
        $this->assertStringContainsString('Davao', $html);
        $this->assertStringContainsString('Confirmed', $html);
    }
}
