<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as checkout } from '@/routes/checkout';
import { index as income } from '@/routes/income';
import { index as kyc } from '@/routes/kyc';
import { index as referral } from '@/routes/referral';
import { index as team } from '@/routes/team';
import { index as wallet } from '@/routes/wallet';
import { index as withdrawals } from '@/routes/withdrawals';
import type { NavItem } from '@/types';

const dashboardUrl = dashboard().url;

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid },
    { title: 'Income', href: income().url, icon: ChartColumn },
    { title: 'Team', href: team().url, icon: Network },
    { title: 'Wallet', href: wallet().url, icon: Wallet },
    { title: 'Withdrawals', href: withdrawals().url, icon: ArrowDownToLine },
    { title: 'Referral link', href: referral().url, icon: Share2 },
    { title: 'Profile & KYC', href: kyc().url, icon: BadgeCheck },
    { title: 'Packages', href: checkout().url, icon: Package },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
