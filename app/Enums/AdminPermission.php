<?php

namespace App\Enums;

/**
 * Granular admin-panel permissions (Spatie, guard: admin).
 */
enum AdminPermission: string
{
    case ManageMembers = 'manage-members';
    case ManageTree = 'manage-tree';
    case ManageSales = 'manage-sales';
    case ManageWithdrawals = 'manage-withdrawals';
    case ManageKyc = 'manage-kyc';
    case ManageSettings = 'manage-settings';
    case ViewReports = 'view-reports';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
