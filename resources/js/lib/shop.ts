import { register } from '@/routes';
import { index as checkout } from '@/routes/checkout';

export type ProductCardData = {
    id: number;
    slug: string;
    name: string;
    brand: string | null;
    category: string | null;
    price: string;
    compareAt: string | null;
    discount: number | null;
    image: string | null;
};

export type ShopCategory = {
    slug: string;
    name: string;
    nameBn: string | null;
};

export type ShopNavigation = {
    categories: ShopCategory[];
    contact: {
        phone?: string;
        email?: string;
        address?: string;
        hours?: string;
    };
    canRegister: boolean;
};

const REF_KEY = 'shop.ref';

/** Remember a ?ref= sponsor code for this browsing session. */
export function rememberReferral(code: string | null): void {
    if (!code) {
        return;
    }

    try {
        sessionStorage.setItem(REF_KEY, code);
    } catch {
        // Storage unavailable (private mode): the code just isn't carried over.
    }
}

function referral(): string | null {
    try {
        return sessionStorage.getItem(REF_KEY);
    } catch {
        return null;
    }
}

/** Sign-up link, carrying the remembered sponsor code and a chosen package. */
export function joinUrl(packageId?: number): string {
    const query: Record<string, string | number> = {};
    const ref = referral();

    if (ref) {
        query.ref = ref;
    }

    if (packageId) {
        query.package = packageId;
    }

    return register.url(Object.keys(query).length ? { query } : undefined);
}

/** "Buy": members go to checkout, visitors sign up first — with the package chosen. */
export function buyPackageUrl(signedIn: boolean, packageId: number): string {
    return signedIn
        ? checkout.url({ query: { package: packageId } })
        : joinUrl(packageId);
}

// Decorative tints for items without a photo yet.
export const tints = [
    'from-sky-100 to-blue-200 text-blue-700 dark:from-sky-950 dark:to-blue-900 dark:text-blue-200',
    'from-emerald-100 to-teal-200 text-teal-700 dark:from-emerald-950 dark:to-teal-900 dark:text-teal-200',
    'from-amber-100 to-orange-200 text-orange-700 dark:from-amber-950 dark:to-orange-900 dark:text-orange-200',
    'from-violet-100 to-fuchsia-200 text-fuchsia-700 dark:from-violet-950 dark:to-fuchsia-900 dark:text-fuchsia-200',
];
