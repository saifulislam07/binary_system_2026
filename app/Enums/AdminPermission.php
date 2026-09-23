<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Granular admin-panel permissions (Spatie, guard: admin).
 */
enum AdminPermission: string
{
    use HasValues;

    case ManageMembers = 'manage-members';
    case ManageTree = 'manage-tree';
    case ManageSales = 'manage-sales';
    case ManageWithdrawals = 'manage-withdrawals';
    case ManageKyc = 'manage-kyc';
    case ManageSettings = 'manage-settings';
    case ViewReports = 'view-reports';
}
