<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $categories = [
            ['name' => 'برقی توکی'],
            ['name' => 'جامې'],
            ['name' => 'خواړه'],
            ['name' => 'کورنی توکی'],
            ['name' => 'دفتری توکی'],
        ];
        foreach ($categories as $cat) {
            Category::create($cat);
        }

        // Customers
        $customers = [
            ['name' => 'احمد خان', 'phone' => '0789123456', 'address' => 'کابل، کوټه سنګي'],
            ['name' => 'محمد عمر', 'phone' => '0789234567', 'address' => 'کندهار، ښار مرکز'],
            ['name' => 'علي رضا', 'phone' => '0789345678', 'address' => 'هرات، انار دره'],
            ['name' => 'کریم داد', 'phone' => '0789456789', 'address' => 'مزار شریف، بازار'],
            ['name' => 'نسیمه', 'phone' => '0789567890', 'address' => 'جلال اباد، نواباد'],
        ];
        foreach ($customers as $cust) {
            Customer::create($cust);
        }

        // Products
        $products = [
            ['name' => 'لمپ', 'category_id' => 1, 'price' => 350, 'stock' => 50, 'description' => 'د برقي لمپ'],
            ['name' => 'تیلویزیون', 'category_id' => 1, 'price' => 15000, 'stock' => 10, 'description' => '۳۲ انچه'],
            ['name' => 'کمپيوټر', 'category_id' => 1, 'price' => 35000, 'stock' => 5, 'description' => 'لپ ټاپ'],
            ['name' => 'ډبل شلوار', 'category_id' => 2, 'price' => 800, 'stock' => 100, 'description' => 'پنبې جامې'],
            ['name' => 'کميض', 'category_id' => 2, 'price' => 650, 'stock' => 80, 'description' => 'نیم استین'],
            ['name' => 'وږه', 'category_id' => 3, 'price' => 95, 'stock' => 200, 'description' => 'یو کیلو'],
            ['name' => 'دال', 'category_id' => 3, 'price' => 120, 'stock' => 150, 'description' => 'سره دال'],
            ['name' => 'چاي', 'category_id' => 3, 'price' => 250, 'stock' => 60, 'description' => 'سیلان چاي'],
            ['name' => 'بستره', 'category_id' => 4, 'price' => 4500, 'stock' => 15, 'description' => 'دبل بستره'],
            ['name' => 'چوکي', 'category_id' => 4, 'price' => 2500, 'stock' => 25, 'description' => 'پلاستیکي'],
            ['name' => 'کاغذ', 'category_id' => 5, 'price' => 150, 'stock' => 300, 'description' => 'A4 کاغذ'],
            ['name' => 'پن', 'category_id' => 5, 'price' => 30, 'stock' => 500, 'description' => 'بال پوینټ'],
        ];
        foreach ($products as $prod) {
            Product::create($prod);
        }

        // Orders
        $order = Order::create([
            'customer_id' => 1,
            'status' => 'completed',
            'total_amount' => 2450,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => 1, 'quantity' => 2, 'unit_price' => 350, 'subtotal' => 700]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => 7, 'quantity' => 5, 'unit_price' => 120, 'subtotal' => 600]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => 8, 'quantity' => 3, 'unit_price' => 250, 'subtotal' => 750]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => 11, 'quantity' => 2, 'unit_price' => 150, 'subtotal' => 300]);

        $order2 = Order::create([
            'customer_id' => 2,
            'status' => 'pending',
            'total_amount' => 15800,
        ]);
        OrderItem::create(['order_id' => $order2->id, 'product_id' => 2, 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000]);
        OrderItem::create(['order_id' => $order2->id, 'product_id' => 12, 'quantity' => 20, 'unit_price' => 30, 'subtotal' => 600]);
        OrderItem::create(['order_id' => $order2->id, 'product_id' => 5, 'quantity' => 2, 'unit_price' => 650, 'subtotal' => 1300]);

        $order3 = Order::create([
            'customer_id' => 3,
            'status' => 'processing',
            'total_amount' => 9500,
        ]);
        OrderItem::create(['order_id' => $order3->id, 'product_id' => 3, 'quantity' => 1, 'unit_price' => 35000, 'subtotal' => 35000]);
    }
}
