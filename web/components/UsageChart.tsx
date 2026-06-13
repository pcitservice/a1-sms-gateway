'use client';

import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type SeriesRow = {
  period: string;
  messages_sent: number;
  messages_delivered: number;
  messages_failed: number;
  messages_received: number;
};

export function UsageChart({ data }: { data: SeriesRow[] }) {
  if (!data?.length) {
    return <div className="grid h-64 place-items-center text-sm text-slate-500">No traffic yet.</div>;
  }
  return (
    <ResponsiveContainer width="100%" height={280}>
      <AreaChart data={data} margin={{ top: 16, right: 8, left: 0, bottom: 0 }}>
        <defs>
          <linearGradient id="g-sent" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"   stopColor="#2563eb" stopOpacity={0.4} />
            <stop offset="100%" stopColor="#2563eb" stopOpacity={0}   />
          </linearGradient>
          <linearGradient id="g-fail" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"   stopColor="#ef4444" stopOpacity={0.4} />
            <stop offset="100%" stopColor="#ef4444" stopOpacity={0}   />
          </linearGradient>
        </defs>
        <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
        <XAxis dataKey="period" tick={{ fontSize: 11 }} />
        <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
        <Tooltip contentStyle={{ borderRadius: 8, fontSize: 12 }} />
        <Area type="monotone" dataKey="messages_sent"   stroke="#2563eb" fill="url(#g-sent)" name="Sent" />
        <Area type="monotone" dataKey="messages_failed" stroke="#ef4444" fill="url(#g-fail)" name="Failed" />
      </AreaChart>
    </ResponsiveContainer>
  );
}
