import {
    BadgeCheck,
    Bell,
    CircleDollarSign,
    Megaphone,
    ShieldAlert,
    ShoppingBag,
    UserCheck,
    UserPlus,
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';

export type AppNotification = {
    id: string;
    kind: string;
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string | null;
};

const icons: Record<string, Component> = {
    registration: UserPlus,
    activation: UserCheck,
    sale: ShoppingBag,
    income: CircleDollarSign,
    withdrawal: Wallet,
    kyc: BadgeCheck,
    security: ShieldAlert,
    announcement: Megaphone,
};

export function notificationIcon(kind: string): Component {
    return icons[kind] ?? Bell;
}

const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const steps: [Intl.RelativeTimeFormatUnit, number][] = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 7],
    ['week', 4.35],
    ['month', 12],
    ['year', Infinity],
];

/** "5 minutes ago", "yesterday", … */
export function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    let value = (new Date(iso).getTime() - Date.now()) / 1000;

    for (const [unit, size] of steps) {
        if (Math.abs(value) < size) {
            return relative.format(Math.round(value), unit);
        }

        value /= size;
    }

    return '';
}
