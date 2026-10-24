import PurchaseController from '@/actions/App/Http/Controllers/PurchaseController';
import SaleController from '@/actions/App/Http/Controllers/SaleController';
import SalesReturnController from '@/actions/App/Http/Controllers/SalesReturnController';
import SupplierReturnController from '@/actions/App/Http/Controllers/SupplierReturnController';

export type ReturnKind = 'sales_return' | 'supplier_return';

/**
 * What differs between the two return kinds on the shared return pages: routes, the
 * permission to execute, and the Uzbek copy (ux-flows §2.3, §2.4).
 */
export const returnKinds = {
    sales_return: {
        controller: SalesReturnController,
        sourceShow: SaleController.show,
        executePermission: 'sales-return.execute',
        title: 'Vozvratlar',
        single: 'Qaytarish',
        description:
            'Sotilgan tovarlar qaytishi. Xodim qoralama saqlaydi, egasi bajaradi: avval mijoz qarzi kamayadi, qolgani pul bilan qaytariladi.',
        source: 'Sotuv',
        sources: 'Bajarilgan sotuvlar',
        moved: 'Sotilgan',
        counterparty: 'Mijoz',
        refundLabel: 'Naqd qaytariladi',
    },
    supplier_return: {
        controller: SupplierReturnController,
        sourceShow: PurchaseController.show,
        executePermission: 'supplier-return.manage',
        title: 'Postavshikka vozvrat',
        single: 'Yetkazib beruvchiga qaytarish',
        description:
            'Xarid qilingan tovarlarni yetkazib beruvchiga qaytarish: avval yetkazib beruvchiga qarz kamayadi, qolgani kassaga qaytadi.',
        source: 'Xarid',
        sources: 'Bajarilgan xaridlar',
        moved: 'Xarid qilingan',
        counterparty: 'Yetkazib beruvchi',
        refundLabel: 'Kassaga qaytadi',
    },
} as const;
