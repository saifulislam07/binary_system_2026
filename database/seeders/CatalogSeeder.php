<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo shop catalog: electronics in six categories with real photos
 * (CC0 images in database/seeders/catalog-images, see CREDITS.md), and the
 * four packages filled with some of them. Demo data only — never run in
 * production; real products are entered by admins. Idempotent by SKU.
 */
class CatalogSeeder extends Seeder
{
    /** [name, name_bn, description] */
    private const CATEGORIES = [
        'audio' => ['Audio', 'অডিও', 'Headphones, earbuds, speakers and microphones'],
        'wearables' => ['Wearables', 'স্মার্ট ঘড়ি ও ব্যান্ড', 'Smart watches and fitness bands'],
        'mobile' => ['Mobile & Tablets', 'মোবাইল ও ট্যাবলেট', 'Phones, tablets and charging'],
        'computer' => ['Computer Accessories', 'কম্পিউটার এক্সেসরিজ', 'Keyboards, mice and stands'],
        'smart-home' => ['Smart Home', 'স্মার্ট হোম', 'Security cameras and lighting'],
        'cameras' => ['Cameras & Drones', 'ক্যামেরা ও ড্রোন', 'Drones and camera lenses'],
    ];

    /**
     * [category, sku, name, price ৳, was ৳|null, featured, images, description, highlights]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int, 4: int|null, 5: bool, 6: list<string>, 7: string, 8: list<string>}>
     */
    private const PRODUCTS = [
        ['audio', 'AUD-HP-001', 'Noise-Cancelling Headphones', 6500, 7900, true, ['noise-cancelling-headphones', 'noise-cancelling-headphones-2'],
            'Over-ear wireless headphones that shut out traffic and office noise, with soft cushions for all-day listening.',
            ['Active noise cancelling', 'Up to 30 hours battery', 'Bluetooth 5.3 with multipoint', 'Foldable, with carry pouch']],
        ['audio', 'AUD-EB-001', 'True Wireless Earbuds', 2450, 2990, true, ['true-wireless-earbuds'],
            'Compact earbuds with a pocket-size charging case and clear calls on the go.',
            ['6 hours play, 24 hours with the case', 'Touch controls', 'IPX4 sweat resistant', 'USB-C charging']],
        ['audio', 'AUD-SP-001', 'Portable Bluetooth Speaker', 1850, null, true, ['portable-bluetooth-speaker'],
            'A rugged fabric-wrapped speaker with deep bass, ready for picnics and rooftops.',
            ['12 hours playtime', 'Water-resistant fabric', 'Built-in strap', 'Pair two for stereo']],
        ['audio', 'AUD-SP-002', 'Waterproof Shower Speaker', 890, null, false, ['waterproof-shower-speaker'],
            'Suction-cup speaker for the bathroom or kitchen — take calls and play music with wet hands.',
            ['Waterproof', 'Strong suction cup', 'Hands-free calling', '6 hours battery']],
        ['audio', 'AUD-EP-001', 'Wired Earphones with Mic', 350, null, false, ['wired-earphones-with-mic'],
            'Everyday in-ear earphones with an inline microphone and a tangle-resistant cable.',
            ['3.5 mm jack', 'Inline mic and button', 'Three ear-tip sizes']],
        ['audio', 'AUD-MC-001', 'Dynamic Vocal Microphone', 3200, null, false, ['dynamic-vocal-microphone'],
            'A sturdy dynamic microphone for singing, podcasts and online classes.',
            ['Cardioid pickup', 'Metal body', 'XLR cable included']],
        ['wearables', 'WR-SW-001', 'AMOLED Smart Watch', 4990, 5990, true, ['amoled-smart-watch'],
            'A bright AMOLED smart watch with call alerts, heart-rate tracking and a week of battery.',
            ['1.8" AMOLED display', 'Heart rate and SpO2', 'Bluetooth calling', 'Up to 7 days battery']],
        ['wearables', 'WR-SW-002', 'Round Smart Watch', 3750, null, false, ['round-smart-watch'],
            'A classic round-face smart watch with sports modes and interchangeable straps.',
            ['1.4" round display', '100+ sports modes', 'IP68 water resistance']],
        ['wearables', 'WR-FB-001', 'Fitness Band', 1650, null, true, ['fitness-band'],
            'A light, slim band that counts steps, sleep and heart rate — and lasts for weeks.',
            ['Step and sleep tracking', 'Heart-rate monitor', 'Up to 14 days battery', '5 ATM water resistance']],
        ['mobile', 'MB-PH-001', 'Smartphone 6.1"', 18500, 19999, true, ['smartphone-6-1'],
            'A 6.1-inch smartphone with a sharp display, all-day battery and a fast charger in the box.',
            ['6.1" display', '128 GB storage', 'Fast charger included', 'Dual SIM']],
        ['mobile', 'MB-TB-001', 'Tablet with Stylus', 32000, null, false, ['tablet-with-stylus'],
            'A 10.9-inch tablet with a pressure-sensitive stylus for notes, drawing and online classes.',
            ['10.9" display', 'Stylus included', '256 GB storage', 'Wi-Fi 6']],
        ['mobile', 'MB-CB-001', 'USB Charging Cable', 250, null, false, ['usb-charging-cable'],
            'A braided 1-metre USB charging and data cable.',
            ['1 metre', 'Fast-charge ready', 'Braided jacket']],
        ['computer', 'PC-MS-001', 'Ergonomic Wireless Mouse', 2250, null, true, ['ergonomic-wireless-mouse'],
            'A sculpted wireless mouse that keeps your wrist relaxed through long workdays.',
            ['Ergonomic shape', 'Silent clicks', 'USB-C rechargeable', 'Works on glass']],
        ['computer', 'PC-KB-001', 'Backlit Keyboard', 2800, null, false, ['backlit-keyboard'],
            'A full-size keyboard with adjustable backlight for work and play at night.',
            ['Adjustable backlight', 'Spill resistant', 'Full-size layout']],
        ['computer', 'PC-LS-001', 'Aluminium Laptop Stand', 1950, null, false, ['aluminium-laptop-stand'],
            'Raises your laptop to eye level for a healthier posture; folds flat for the bag.',
            ['Aluminium alloy', 'Six height levels', 'Fits 10–17" laptops']],
        ['smart-home', 'SH-CM-001', 'Outdoor Security Camera', 4200, null, true, ['outdoor-security-camera'],
            'A weatherproof Wi-Fi camera with night vision and motion alerts on your phone.',
            ['1080p video', 'Night vision', 'Motion alerts', 'IP66 weatherproof']],
        ['smart-home', 'SH-BL-001', 'LED Filament Bulb', 320, 390, false, ['led-filament-bulb', 'led-filament-bulb-2'],
            'A warm vintage-style LED bulb that uses a fraction of the power of an old bulb.',
            ['6 W (like a 60 W bulb)', 'Warm white', 'E27 base']],
        ['cameras', 'CM-DR-001', '4K Camera Drone', 38500, 42000, true, ['4k-camera-drone', '4k-camera-drone-2'],
            'A camera drone that shoots steady 4K video from the sky, with GPS return-home.',
            ['4K camera on a gimbal', 'GPS return-home', 'Up to 25 minutes flight']],
        ['cameras', 'CM-LN-001', 'Zoom Camera Lens', 21000, null, false, ['zoom-camera-lens'],
            'A versatile standard zoom lens for travel, events and portraits.',
            ['24–105 mm range', 'Image stabilisation', 'Weather sealed']],
    ];

    /** package name => [sku => quantity] */
    private const PACKAGE_CONTENTS = [
        'Basic' => ['AUD-EP-001' => 1, 'MB-CB-001' => 1],
        'Standard' => ['AUD-EB-001' => 1, 'WR-FB-001' => 1, 'MB-CB-001' => 1],
        'Premium' => ['WR-SW-001' => 1, 'AUD-SP-001' => 1, 'PC-MS-001' => 1],
        'Business' => ['MB-TB-001' => 1, 'AUD-HP-001' => 1, 'PC-KB-001' => 1],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CatalogSeeder is demo data and must not run in production.');
        }

        $categories = [];

        foreach (self::CATEGORIES as $key => [$name, $nameBn, $description]) {
            $categories[$key] = Category::query()->firstOrCreate(
                ['name' => $name],
                ['name_bn' => $nameBn, 'description' => $description, 'sort_order' => count($categories) + 1, 'is_active' => true],
            );
        }

        foreach (self::PRODUCTS as $i => [$category, $sku, $name, $price, $was, $featured, $images, $description, $highlights]) {
            $product = Product::query()->firstOrCreate(['sku' => $sku], [
                'category_id' => ($categories[$category] ?? throw new RuntimeException("Unknown category {$category}."))->id,
                'name' => $name,
                'price' => $price * 100,
                'compare_at_price' => $was === null ? null : $was * 100,
                'is_active' => true,
                'is_featured' => $featured,
                'sort_order' => $i + 1,
                'description' => $description,
                'highlights' => implode("\n", $highlights),
            ]);

            if (! $product->hasMedia('images')) {
                foreach ($images as $image) {
                    $product->addMedia($this->imagePath($image))->preservingOriginal()->toMediaCollection('images');
                }
            }
        }

        foreach (self::PACKAGE_CONTENTS as $packageName => $contents) {
            $package = Package::query()->where('name', $packageName)->first();

            if ($package === null || $package->products()->exists()) {
                continue;
            }

            $ids = Product::query()->whereIn('sku', array_keys($contents))->pluck('id', 'sku');
            $package->products()->sync(collect($contents)->mapWithKeys(fn (int $qty, string $sku) => [$ids[$sku] => ['quantity' => $qty]])->all());

            // The package photo is its first product's photo.
            if (! $package->hasMedia('image')) {
                $cover = $this->coverImageFor((string) array_key_first($contents));

                if ($cover !== null) {
                    $package->addMedia($this->imagePath($cover))->preservingOriginal()->toMediaCollection('image');
                }
            }
        }
    }

    private function coverImageFor(string $sku): ?string
    {
        foreach (self::PRODUCTS as $row) {
            if ($row[1] === $sku) {
                return $row[6][0];
            }
        }

        return null;
    }

    private function imagePath(string $name): string
    {
        $path = database_path("seeders/catalog-images/{$name}.jpg");

        if (! is_file($path)) {
            throw new RuntimeException("Missing catalog image {$path}.");
        }

        return $path;
    }
}
