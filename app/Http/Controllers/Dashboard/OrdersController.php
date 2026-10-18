<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Screen;
use App\Models\Order;
use App\Models\Page;
use App\Services\Billing;
use App\Services\Designs;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Plans;
use Naluz\Auth\Auth;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Buying a plan for a page: create (or reuse) the order, then hand over to the payment gateway. */
final class OrdersController
{
    use OwnsPages;

    public function store(string $slug, ServerRequestInterface $request, Auth $auth, Plans $plans, Billing $billing, PaymentGateway $gateway): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $body = Request::body($request);
        $plan = (string) ($body['plan'] ?? '');
        $period = (string) ($body['period'] ?? '');
        if (!$plans->canBuy($page, $plan, $period)) {
            throw new ValidationException(['plan' => ['That plan cannot be bought for this page.']], ['plan' => $plan]);
        }
        // the page's template has to come with the plan, or the page would go live in another one
        $template = Designs::layout($page->layout);
        if (!$plans->allowsTemplate($plan, $template)) {
            throw new ValidationException(['plan' => [sprintf(
                'Your page uses the %s template, which comes with %s. Choose one of those plans, or pick another template first.',
                Designs::LAYOUTS[$template]['name'],
                $plans->names($plans->plansWithTemplate($template)),
            )]], ['plan' => $plan]);
        }

        // Clicking "buy" twice must not create two orders to pay.
        $order = Order::query()->where('user_id', (int) $auth->id())->where('page_id', $page->id)->where('plan', $plan)
            ->where('period', $period)->where('status', Order::PENDING)->where('provider', $gateway->name())->first();
        if (!$order instanceof Order) {
            $order = $billing->createOrder((int) $auth->id(), $page, $plan, $period, $gateway->name());
        }
        return Response::redirect($gateway->checkoutUrl($order), 303);
    }

    public function show(string $reference, Auth $auth, Plans $plans, PaymentGateway $gateway): ResponseInterface
    {
        $order = $this->ownedOrder($auth, $reference);
        $page = $order->page_id !== null ? Page::query()->where('id', $order->page_id)->first() : null;
        return Screen::render('dashboard.order', [
            'order' => $order,
            'page' => $page instanceof Page ? $page : null,
            'plan' => $plans->get($order->plan),
            'canFakePay' => $gateway instanceof FakeGateway && $order->provider === 'fake' && $order->status === Order::PENDING,
            'instructions' => (string) config('saas.payments.manual_instructions'),
        ]);
    }

    /** Test checkout only (PAYMENTS_DRIVER=fake, never in production). */
    public function fakePay(string $reference, Auth $auth, PaymentGateway $gateway, Billing $billing): ResponseInterface
    {
        $order = $this->ownedOrder($auth, $reference);
        if (!$gateway instanceof FakeGateway || $order->provider !== 'fake') {
            throw new HttpException(404, 'Not Found');
        }
        $billing->markPaid($order, 'fake', 'fake_' . bin2hex(random_bytes(8)));

        $page = $order->page_id !== null ? Page::query()->where('id', $order->page_id)->first() : null;
        return Screen::back($page instanceof Page ? $this->editUrl($page, 'plan') : '/dashboard', 'Payment received. Your page is live!');
    }

    private function ownedOrder(Auth $auth, string $reference): Order
    {
        $order = Order::query()->where('reference', $reference)->where('user_id', (int) $auth->id())->first();
        if (!$order instanceof Order) {
            throw new HttpException(404, 'Order not found.');
        }
        return $order;
    }
}
