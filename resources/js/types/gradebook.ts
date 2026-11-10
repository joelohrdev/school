export type TrendPoint = { date: string; percent: number | null };

export type ClassRow = {
    id: number;
    course: string;
    period: string | null;
    room: string | null;
    teacher: string | null;
    teacherEmail: string | null;
    percent: number | null;
    letter: string | null;
    term: string | null;
    trend: TrendPoint[];
};

export type MissingItem = {
    id: number;
    name: string;
    course: string;
    sectionId: number;
    dueOn: string | null;
    daysOverdue: number | null;
};

export type UpcomingItem = {
    id: number;
    name: string;
    course: string;
    sectionId: number;
    dueOn: string | null;
    daysUntil: number | null;
};

export type ReportCardRow = {
    id: number;
    term: string | null;
    title: string | null;
    startsOn: string | null;
    endsOn: string | null;
    percent: number | null;
    letter: string | null;
    comment: string | null;
    current: boolean;
};

export type HistoryRow = {
    date: string;
    average: number | null;
    missing: number;
    late: number;
};

export type StudentRef = {
    id: number;
    name: string;
    fullName: string;
    gradeLevel: number | null;
};

export type StudentSummary = StudentRef & {
    missingCount: number;
    lateCount: number;
    missing: MissingItem[];
    upcoming: UpcomingItem[];
    averageTrend: TrendPoint[];
    classes: ClassRow[];
};

export type AssignmentRow = {
    id: number;
    name: string;
    description: string | null;
    course: string;
    sectionId: number;
    category: string | null;
    dueOn: string | null;
    pointsPossible: number | null;
    score: string | null;
    percent: number | null;
    letter: string | null;
    missing: boolean;
    late: boolean;
    exempt: boolean;
    collected: boolean;
    comment: string | null;
    scoredAt: string | null;
};

export type Attendance = {
    total: number;
    byCode: {
        code: string | null;
        description: string | null;
        count: number;
    }[];
    recent: { date: string; code: string | null; description: string | null }[];
};

export type SyncRunRow = {
    id: number;
    status: 'running' | 'succeeded' | 'failed' | 'skipped';
    trigger: 'cli' | 'schedule' | 'manual';
    dryRun: boolean;
    startedAt: string;
    finishedAt: string | null;
    durationSeconds: number | null;
    totals: { created: number; updated: number; skipped: number };
    alerts: number;
    error: string | null;
};

export type AlertType =
    | 'missing'
    | 'late'
    | 'missing_resolved'
    | 'score_posted'
    | 'grade_drop'
    | 'absence'
    | 'comment';

export type AlertRow = {
    id: number;
    type: AlertType;
    student: { id: number; name: string };
    payload: {
        section_id?: number;
        course_name?: string;
        assignment_name?: string;
        due_on?: string | null;
        score?: string | null;
        percent?: number | null;
        letter_grade?: string | null;
        points_possible?: number | null;
        previous_percent?: number;
        previous_on?: string;
        drop?: number;
        comment?: string | null;
        date?: string;
        code?: string | null;
        description?: string | null;
    };
    createdAt: string;
    readAt: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
