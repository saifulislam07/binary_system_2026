import {
    ArrowDownToLine,
    BadgeCheck,
    ChartColumn,
    LayoutGrid,
    Network,
    Package,
    Share2,
    Wallet,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as checkout } from '@/routes/checkout';
import { index as income } from '@/routes/income';
import { index as kyc } from '@/routes/kyc';
import { index as referral } from '@/routes/referral';
import { index as team } from '@/routes/team';
import { index as wallet } from '@/routes/wallet';
import { index as withdrawals } from '@/routes/withdrawals';
import type { NavItem } from '@/types';

/** The member app's main menu (sidebar and header layouts share it). */
export const memberNavItems: NavItem[] = [
    { title: 'Dashboard · ড্যাশবোর্ড', href: dashboard().url, icon: LayoutGrid },
    { title: 'Income · আয়', href: income().url, icon: ChartColumn },
    { title: 'Team · দল', href: team().url, icon: Network },
    { title: 'Wallet · ওয়ালেট', href: wallet().url, icon: Wallet },
    {
        title: 'Withdrawals · উত্তোলন',
        href: withdrawals().url,
        icon: ArrowDownToLine,
    },
    { title: 'Referral link · রেফারেল', href: referral().url, icon: Share2 },
    { title: 'Profile & KYC · প্রোফাইল', href: kyc().url, icon: BadgeCheck },
    { title: 'Packages · প্যাকেজ', href: checkout().url, icon: Package },
];
