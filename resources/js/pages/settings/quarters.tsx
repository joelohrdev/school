import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import QuarterController from '@/actions/App/Http/Controllers/Settings/QuarterController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDay } from '@/lib/format';
import { edit as editQuarters } from '@/routes/quarters';
import type { QuarterOption } from '@/types/gradebook';

type Row = { name: string; starts_on: string; ends_on: string };

const BLANK: Row[] = ['Q1', 'Q2', 'Q3', 'Q4'].map((name) => ({
    name,
    starts_on: '',
    ends_on: '',
}));

export default function Quarters({
    fromPowerSchool,
    powerSchoolQuarters,
    quarters,
}: {
    fromPowerSchool: boolean;
    powerSchoolQuarters: QuarterOption[];
    quarters: Row[];
}) {
    return (
        <>
            <Head title="School quarters" />
            <h1 className="sr-only">School quarters</h1>

            {fromPowerSchool ? (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="School quarters"
                        description="PowerSchool sends the quarter dates, so there's nothing to enter. Grades, assignments, trends and attendance are grouped by these."
                    />
                    <ul className="divide-y divide-rule border-y border-rule">
                        {powerSchoolQuarters.map((q) => (
                            <li
                                key={q.key}
                                className="flex items-baseline justify-between gap-3 py-2.5"
                            >
                                <span className="font-semibold">{q.name}</span>
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {formatDay(q.startsOn)} to{' '}
                                    {formatDay(q.endsOn)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : (
                <QuarterForm initial={quarters.length ? quarters : BLANK} />
            )}
        </>
    );
}

function QuarterForm({ initial }: { initial: Row[] }) {
    const form = useForm<{ quarters: Row[] }>({ quarters: initial });
    const rows = form.data.quarters;
    const errors = form.errors as Record<string, string | undefined>;

    const set = (i: number, key: keyof Row, value: string) =>
        form.setData(
            'quarters',
            rows.map((r, j) => (j === i ? { ...r, [key]: value } : r)),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.submit(QuarterController.update(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Heading
                variant="small"
                title="School quarters"
                description="PowerSchool isn't sending quarter dates, so enter them from the school calendar. Grades, assignments, trends and attendance are grouped by these."
            />

            <div className="space-y-5">
                {rows.map((r, i) => (
                    <fieldset
                        key={i}
                        className="grid grid-cols-[5rem_1fr_1fr_auto] items-end gap-2 sm:gap-3"
                    >
                        <legend className="sr-only">Quarter {i + 1}</legend>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`q${i}-name`}>Name</Label>
                            <Input
                                id={`q${i}-name`}
                                value={r.name}
                                maxLength={20}
                                onChange={(e) => set(i, 'name', e.target.value)}
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`q${i}-start`}>First day</Label>
                            <Input
                                id={`q${i}-start`}
                                type="date"
                                value={r.starts_on}
                                onChange={(e) =>
                                    set(i, 'starts_on', e.target.value)
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`q${i}-end`}>Last day</Label>
                            <Input
                                id={`q${i}-end`}
                                type="date"
                                value={r.ends_on}
                                min={r.starts_on || undefined}
                                onChange={(e) =>
                                    set(i, 'ends_on', e.target.value)
                                }
                                required
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Remove ${r.name || `quarter ${i + 1}`}`}
                            onClick={() =>
                                form.setData(
                                    'quarters',
                                    rows.filter((_, j) => j !== i),
                                )
                            }
                        >
                            <Trash2 className="size-4" aria-hidden />
                        </Button>
                        <InputError
                            className="col-span-4"
                            message={
                                errors[`quarters.${i}.name`] ??
                                errors[`quarters.${i}.starts_on`] ??
                                errors[`quarters.${i}.ends_on`]
                            }
                        />
                    </fieldset>
                ))}
            </div>

            <InputError message={errors.quarters} />

            <div className="flex flex-wrap items-center gap-3">
                <Button disabled={form.processing}>Save quarters</Button>
                {rows.length < 8 && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            form.setData('quarters', [
                                ...rows,
                                {
                                    name: `Q${rows.length + 1}`,
                                    starts_on: '',
                                    ends_on: '',
                                },
                            ])
                        }
                    >
                        <Plus className="size-4" aria-hidden />
                        Add quarter
                    </Button>
                )}
            </div>
        </form>
    );
}

Quarters.layout = {
    breadcrumbs: [{ title: 'School quarters', href: editQuarters() }],
};
