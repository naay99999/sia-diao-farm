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
