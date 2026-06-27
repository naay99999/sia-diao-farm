export type FruitType = {
    id: number;
    name: string;
    varieties_count?: number;
    created_at: string | null;
    updated_at: string | null;
};

export type FruitVariety = {
    id: number;
    fruit_type_id: number;
    name: string;
    days_to_harvest: number;
    fruit_type?: FruitType;
};

export type ActivityType = {
    id: number;
    name: string;
    activities_count?: number;
    created_at: string | null;
    updated_at: string | null;
};

export type CropCycleStage =
    | 'soil_prep'
    | 'flowering'
    | 'fruiting'
    | 'ready_to_harvest'
    | 'harvested';

export type CropCycleStatus = 'active' | 'closed';

export type CropCycle = {
    id: number;
    plot_id: number;
    fruit_variety_id: number;
    label: string;
    stage: CropCycleStage;
    status: CropCycleStatus;
    flowering_date: string | null;
    expected_harvest_date: string | null;
    started_at: string;
    closed_at: string | null;
    notes: string | null;
    plot?: Plot;
    fruit_variety?: FruitVariety;
    activities?: Activity[];
    expenses?: Expense[];
};

export type Plot = {
    id: number;
    name: string;
    fruit_variety_id: number;
    tree_count: number;
    planted_at: string | null;
    area_rai: string | null;
    notes: string | null;
    tree_age_years: number | null;
    fruit_variety?: FruitVariety;
    active_crop_cycle?: CropCycle | null;
    crop_cycles?: CropCycle[];
};

export type ExpenseScope = 'direct' | 'overhead';

export const expenseScopeLabels: Record<ExpenseScope, string> = {
    direct: 'ต้นทุนตรง (ผูกรอบการผลิต)',
    overhead: 'ค่าส่วนกลาง',
};

export type ExpenseCategory = {
    id: number;
    name: string;
    default_scope: ExpenseScope;
    created_at: string | null;
    updated_at: string | null;
};

export type Activity = {
    id: number;
    crop_cycle_id: number;
    activity_type_id: number;
    performed_on: string;
    notes: string | null;
    activity_type?: ActivityType;
};

export type Expense = {
    id: number;
    expense_category_id: number;
    amount: string;
    spent_on: string;
    description: string | null;
    crop_cycle_id: number | null;
    activity_id: number | null;
    scope: ExpenseScope;
    expense_category?: ExpenseCategory;
};

export const cropCycleStageLabels: Record<CropCycleStage, string> = {
    soil_prep: 'บำรุงดิน',
    flowering: 'ออกดอก',
    fruiting: 'ติดผล',
    ready_to_harvest: 'พร้อมเก็บเกี่ยว',
    harvested: 'เก็บเกี่ยวแล้ว',
};
