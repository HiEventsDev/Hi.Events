export type RecentScanStatus = "success" | "duplicate" | "error";

export interface RecentScan {
    id: string;
    name: string;
    code: string;
    status: RecentScanStatus;
    timestamp: number;
}
