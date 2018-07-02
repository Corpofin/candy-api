<?php

namespace GetCandy\Api\Core\Orders\Listeners;

use DB;
use GetCandy\Api\Core\Discounts\Factory;
use GetCandy\Api\Core\Orders\Events\OrderSavedEvent;

class RefreshOrderListener
{
    /**
     * Handle the event.
     *
     * @param  OrderShipped  $event
     * @return void
     */
    public function handle(OrderSavedEvent $event)
    {
        $order = $event->order;

        $totals = $order->lines()->select(
            DB::RAW('SUM(line_total) as line_total'),
            DB::RAW('SUM(tax_total) as tax_total'),
            DB::RAW('SUM(discount_total) as discount_total'),
            DB::RAW('SUM(line_total) + SUM(tax_total) - SUM(discount_total) as grand_total')
        )->whereIsShipping(false)->first()->toArray();

        $totals['delivery_total'] = 0;

        $shipping = $order->lines()
            ->select(
                'line_total',
                'tax_total',
                'discount_total',
                DB::RAW('line_total + tax_total - discount_total as grand_total')
            )->whereIsShipping(true)->first();

        if ($shipping) {
            $totals['delivery_total'] = $shipping->line_total;
            $totals['tax_total'] += $shipping->tax_total;
            $totals['discount_total'] += $shipping->discount_total;
            $totals['grand_total'] += $shipping->grand_total;
        }

        $order->update([
            'delivery_total' => $totals['delivery_total'] ?? 0,
            'tax_total' => $totals['tax_total'] ?? 0,
            'discount_total' => $totals['discount_total'] ?? 0,
            'sub_total' => $totals['line_total'] ?? 0,
            'order_total' => $totals['grand_total'] ?? 0
        ]);
    }
}