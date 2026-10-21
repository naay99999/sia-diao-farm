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
    trees?: Pick<Tree, 'id' | 'code'>[];
};

export const careTypeLabels: Record<CareType, string> = {
    fertilizer: 'ใส่ปุ๋ย',
    pesticide: 'ฉีดยา',
    other: 'อื่น ๆ',
};
