export type Orchard = {
    id: number;
    name: string;
    location: string | null;
    notes: string | null;
};

export type Tree = {
    id: number;
    orchard_id: number;
    code: string;
    variety: string | null;
    planted_on: string | null;
    removed_on: string | null;
    notes: string | null;
};

export type OrchardSummary = Orchard & {
    trees_count: number;
    current_season: string | null;
    current_yield_kg: number;
};

export type TreeSeasonYield = {
    treeId: number;
    code: string;
    weightKg: number;
    fruitCount: number;
};

export type Season = {
    id: number;
    orchard_id: number;
    name: string;
    started_on: string;
    ended_on: string | null;
    notes: string | null;
};

export type CareType = 'fertilizer' | 'pesticide' | 'other';

export type CareRecord = {
    id: number;
    orchard_id: number;
    type: CareType;
    performed_on: string;
    product: string | null;
    quantity: string | null;
    notes: string | null;
    trees?: Pick<Tree, 'id' | 'code' | 'removed_on'>[];
    issue_ids?: number[];
};

export const careTypeLabels: Record<CareType, string> = {
    fertilizer: 'ใส่ปุ๋ย',
    pesticide: 'ฉีดยา',
    other: 'อื่น ๆ',
};

export type Harvest = {
    id: number;
    tree_id: number;
    harvested_on: string;
    weight_kg: string;
    fruit_count: number | null;
    notes: string | null;
};

export type FindingKind = 'problem' | 'stage';

export type Finding = {
    id: number;
    name: string;
    kind: FindingKind;
};

export type Severity = 'low' | 'medium' | 'high';

export const severityLabels: Record<Severity, string> = {
    low: 'น้อย',
    medium: 'กลาง',
    high: 'มาก',
};

export type FollowUpOutcome = 'resolved' | 'not_resolved';

export type IssueSummary = {
    id: number;
    treeId: number;
    treeCode: string;
    finding: string;
    openedOn: string;
    followUpOn: string;
    isDue: boolean;
};

export type LatestStage = {
    finding: string;
    observedOn: string;
    inCurrentSeason: boolean;
};

export type Observation = {
    id: number;
    finding_id: number;
    tree_issue_id: number | null;
    observed_on: string;
    severity: Severity | null;
    outcome: FollowUpOutcome | null;
    notes: string | null;
};

type TimelineEntryBase = {
    id: number;
    date: string;
    title: string;
    detail: string | null;
    fromAi: boolean;
    aiBy: string | null;
};

export type TimelineEntry =
    | (TimelineEntryBase & { kind: 'care'; record: CareRecord })
    | (TimelineEntryBase & { kind: 'harvest'; record: Harvest })
    | (TimelineEntryBase & {
          kind: 'observation';
          photos: string[];
          record: Observation;
      });

export type SeasonYield = {
    seasonId: number;
    seasonName: string;
    weightKg: number;
    fruitCount: number;
};

export type Sale = {
    id: number;
    orchard_id: number;
    sold_on: string;
    buyer_name: string | null;
    weight_kg: string;
    amount: string;
    notes: string | null;
};

export type ExpenseCategory =
    'fertilizer' | 'pesticide' | 'labor' | 'utilities' | 'equipment' | 'other';

export type Expense = {
    id: number;
    season_id: number | null;
    category: ExpenseCategory;
    spent_on: string;
    amount: string;
    notes: string | null;
    season: (Season & { orchard: Orchard }) | null;
};

export type TreeStatus = 'due' | 'issue' | 'normal';

export type TreeMapTree = {
    id: number;
    code: string;
    position: number | null;
    status: TreeStatus;
};

export type TreeMapRow = {
    row: string | null;
    trees: TreeMapTree[];
};

export type OrchardCareLatest = {
    date: string;
    product: string | null;
};

export type OrchardOverview = {
    id: number;
    name: string;
    treeCount: number;
    statusCounts: Record<TreeStatus, number>;
    layout: TreeMapRow[];
    season: {
        id: number;
        name: string;
        startedOn: string;
        yieldKg: number;
        salesTotal: number;
        expensesTotal: number;
    } | null;
    latestFertilizer: OrchardCareLatest | null;
    latestPesticide: OrchardCareLatest | null;
};

export type FarmIssueSummary = IssueSummary & {
    orchardId: number;
    orchardName: string;
};

export type RecentAiRecord = {
    kind: string;
    title: string;
    subject: string;
    recordedOn: string;
    href: string | null;
    by: string | null;
};

export type RecordType = 'care' | 'observations' | 'harvests' | 'sales';

export type RecordFilterValues = {
    orchard_id: number | null;
    season_id: number | null;
    from: string | null;
    to: string | null;
    created_by: number | null;
    via: 'app' | 'mcp' | null;
};

export type RecordFilterOptions = {
    orchards: Pick<Orchard, 'id' | 'name'>[];
    seasons: { id: number; label: string }[];
    members: { id: number; name: string }[];
};

export type RecordProvenance = {
    fromAi: boolean;
    aiBy: string | null;
    createdBy: string | null;
};

type OrchardRef = Pick<Orchard, 'id' | 'name'>;
type TreeRef = Pick<Tree, 'id' | 'code'>;

export type CareRow = CareRecord &
    RecordProvenance & {
        typeLabel: string;
        orchard: OrchardRef;
    };

export type ObservationRow = Observation &
    RecordProvenance & {
        finding: Pick<Finding, 'id' | 'name' | 'kind'>;
        issueStatus: 'open' | 'resolved' | null;
        tree: TreeRef;
        orchard: OrchardRef;
    };

export type HarvestRow = Harvest &
    RecordProvenance & {
        tree: TreeRef;
        orchard: OrchardRef;
    };

export type SaleRow = Sale &
    RecordProvenance & {
        orchard: OrchardRef;
    };

export type ExpenseRow = Expense & RecordProvenance;

export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export type TrashItem = {
    type: string;
    id: number;
    kind: string;
    title: string;
    subject: string;
    href: string | null;
    deletedAt: string;
};
