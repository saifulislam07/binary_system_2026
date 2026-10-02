<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\MembershipSection;
use Illuminate\Support\Facades\DB;

/**
 * Admin edits to the text sections of the public membership page. Every
 * change is logged with old/new values; sections are hidden, never
 * deleted. The earnings disclaimer is not a section — it can't be edited.
 */
class MembershipPageService
{
    private const FIELDS = ['title_en', 'title_bn', 'body_en', 'body_bn', 'sort_order', 'is_active'];

    /**
     * @param  array{title_en: string, title_bn: string|null, body_en: string, body_bn: string|null, sort_order: int, is_active: bool}  $data
     */
    public function save(?MembershipSection $section, array $data, Admin $admin): MembershipSection
    {
        return DB::transaction(function () use ($section, $data, $admin) {
            $section ??= new MembershipSection;
            $creating = ! $section->exists;
            $before = $creating ? [] : $section->only(self::FIELDS);

            $section->fill($data)->save();

            $after = $section->only(self::FIELDS);
            $normal = fn (mixed $v) => is_bool($v) ? (int) $v : ($v === null ? null : (string) $v);
            $changed = array_keys(array_filter($after, fn ($value, $key) => ! array_key_exists($key, $before) || $normal($before[$key]) !== $normal($value), ARRAY_FILTER_USE_BOTH));

            if ($changed !== []) {
                activity('membership_page')
                    ->performedOn($section)
                    ->causedBy($admin)
                    ->withProperties([
                        'old' => array_intersect_key($before, array_flip($changed)),
                        'attributes' => array_intersect_key($after, array_flip($changed)),
                    ])
                    ->log($creating ? 'Membership page section created' : 'Membership page section updated');
            }

            return $section;
        });
    }

    /**
     * The starting sections for a fresh install (ReferenceDataSeeder). Never
     * touches a page an admin has already set up.
     */
    public function seedDefaults(): void
    {
        if (MembershipSection::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $i => [$titleEn, $titleBn, $bodyEn, $bodyBn]) {
            MembershipSection::query()->create([
                'title_en' => $titleEn,
                'title_bn' => $titleBn,
                'body_en' => $bodyEn,
                'body_bn' => $bodyBn,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }
    }

    /** [title_en, title_bn, body_en, body_bn] */
    private const DEFAULTS = [
        [
            'How membership works',
            'সদস্যপদ কীভাবে কাজ করে',
            '<ul><li><strong>Sponsor:</strong> you join with the member ID of the person who invited you, and buy one package. Paying activates your account and gives you your own member ID.</li><li><strong>Binary tree:</strong> each member has one left and one right position. If the side you choose under your sponsor is taken, you are placed in the first free spot further down that side.</li><li><strong>Business volume (BV):</strong> every package carries a business volume. When someone in your team buys a package, its BV is added to your left or right side.</li></ul>',
            '<ul><li><strong>স্পনসর:</strong> যিনি আপনাকে আমন্ত্রণ জানিয়েছেন তার মেম্বার আইডি দিয়ে যোগ দিন এবং একটি প্যাকেজ কিনুন। পেমেন্ট করলে আপনার অ্যাকাউন্ট সক্রিয় হয় এবং আপনি নিজের মেম্বার আইডি পান।</li><li><strong>বাইনারি ট্রি:</strong> প্রত্যেক সদস্যের নিচে একটি বাম ও একটি ডান অবস্থান থাকে। স্পনসরের নিচে আপনার বেছে নেওয়া দিক পূর্ণ থাকলে সেই দিকের নিচে প্রথম খালি জায়গায় আপনাকে বসানো হয়।</li><li><strong>বিজনেস ভলিউম (BV):</strong> প্রতিটি প্যাকেজের একটি বিজনেস ভলিউম আছে। আপনার দলের কেউ প্যাকেজ কিনলে তার BV আপনার বাম বা ডান দিকে যোগ হয়।</li></ul>',
        ],
        [
            'Fair play',
            'স্বচ্ছ নিয়ম',
            '<ul><li>Commission is earned only from genuine product sales — never for signing people up.</li><li>If a sale is refunded, the volume and commission it generated are reversed.</li><li>One account per person: a mobile number or NID can belong to only one active member.</li><li>Every withdrawal is reviewed by an admin before it is paid.</li></ul>',
            '<ul><li>কমিশন শুধু প্রকৃত পণ্য বিক্রি থেকে আসে — শুধু সদস্য আনার জন্য কিছু দেওয়া হয় না।</li><li>কোনো বিক্রয় ফেরত হলে তা থেকে পাওয়া ভলিউম ও কমিশন ফেরত নেওয়া হয়।</li><li>একজনের একটি অ্যাকাউন্ট: একটি মোবাইল নম্বর বা জাতীয় পরিচয়পত্র শুধু একজন সক্রিয় সদস্যের হতে পারে।</li><li>প্রতিটি উত্তোলন পরিশোধের আগে একজন অ্যাডমিন যাচাই করেন।</li></ul>',
        ],
    ];
}
