export type ClientJob = {
    id: number;
    title: string;
    site_address: string;
    status: 'sent' | 'active' | 'completed' | 'cancelled';
    quote_cents: number;
    share_token: string;
    start_date: string | null;
    target_date: string | null;
    accepted_at: string | null;
    client_name: string;
    cancel_reason: string | null;
};

export type Contractor = {
    name: string;
    logo_url: string | null;
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_no: string | null;
};

export type JobSummary = {
    quote_cents: number;
    total_cents: number;
    recorded_cents: number;
    confirmed_cents: number;
    balance_cents: number;
};
