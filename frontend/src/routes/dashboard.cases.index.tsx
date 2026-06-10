import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { Download, Eye, Filter, Search } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/status-badge";
import { mockCases } from "@/mock-data";
import { toast } from "sonner";

export const Route = createFileRoute("/dashboard/cases/")({
  component: CasesPage,
  head: () => ({ meta: [{ title: "Cases — SafeCampus Admin" }] }),
});

const PAGE_SIZE = 8;

function CasesPage() {
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [faculty, setFaculty] = useState("all");
  const [category, setCategory] = useState("all");
  const [anon, setAnon] = useState("all");
  const [sort, setSort] = useState("newest");
  const [page, setPage] = useState(1);

  const faculties = Array.from(new Set(mockCases.map((c) => c.faculty)));

  const filtered = useMemo(() => {
    let data = mockCases.filter((c) => {
      if (status !== "all" && c.status !== status) return false;
      if (faculty !== "all" && c.faculty !== faculty) return false;
      if (category !== "all" && c.category !== category) return false;
      if (anon === "anonymous" && !c.anonymous) return false;
      if (anon === "named" && c.anonymous) return false;
      if (q && !`${c.id} ${c.reporterName} ${c.faculty} ${c.assignedOfficer}`.toLowerCase().includes(q.toLowerCase()))
        return false;
      return true;
    });
    data = [...data].sort((a, b) =>
      sort === "newest" ? +new Date(b.date) - +new Date(a.date) : +new Date(a.date) - +new Date(b.date),
    );
    return data;
  }, [q, status, faculty, category, anon, sort]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const pageData = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Case management</h1>
          <p className="text-sm text-muted-foreground">
            Search, filter, and triage all incoming reports.
          </p>
        </div>
        <Button variant="outline" onClick={() => toast.success("Export prepared (demo)")}>
          <Download className="mr-2 h-4 w-4" /> Export
        </Button>
      </div>

      <Card>
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search by ID, reporter, faculty..."
                value={q}
                onChange={(e) => {
                  setQ(e.target.value);
                  setPage(1);
                }}
                className="pl-9"
              />
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Filter className="h-4 w-4 text-muted-foreground" />
              <Select value={status} onValueChange={(v) => { setStatus(v); setPage(1); }}>
                <SelectTrigger className="w-[150px]"><SelectValue placeholder="Status" /></SelectTrigger>
                <SelectContent>
                  {["all","received","verification","investigation","mediation","resolved","closed"].map(s=>(
                    <SelectItem key={s} value={s} className="capitalize">{s}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={faculty} onValueChange={(v) => { setFaculty(v); setPage(1); }}>
                <SelectTrigger className="w-[180px]"><SelectValue placeholder="Faculty" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All faculties</SelectItem>
                  {faculties.map(f=> <SelectItem key={f} value={f}>{f}</SelectItem>)}
                </SelectContent>
              </Select>
              <Select value={category} onValueChange={(v) => { setCategory(v); setPage(1); }}>
                <SelectTrigger className="w-[150px]"><SelectValue placeholder="Category" /></SelectTrigger>
                <SelectContent>
                  {["all","verbal","physical","digital","stalking","discrimination","other"].map(c=>(
                    <SelectItem key={c} value={c} className="capitalize">{c}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={anon} onValueChange={(v) => { setAnon(v); setPage(1); }}>
                <SelectTrigger className="w-[150px]"><SelectValue placeholder="Reporter" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All reporters</SelectItem>
                  <SelectItem value="anonymous">Anonymous only</SelectItem>
                  <SelectItem value="named">Named only</SelectItem>
                </SelectContent>
              </Select>
              <Select value={sort} onValueChange={setSort}>
                <SelectTrigger className="w-[140px]"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="newest">Newest first</SelectItem>
                  <SelectItem value="oldest">Oldest first</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="px-3 py-2 text-left">Report ID</th>
                  <th className="px-3 py-2 text-left">Reporter</th>
                  <th className="px-3 py-2 text-left">Faculty</th>
                  <th className="px-3 py-2 text-left">Category</th>
                  <th className="px-3 py-2 text-left">Date</th>
                  <th className="px-3 py-2 text-left">Officer</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                {pageData.map((c) => (
                  <tr key={c.id} className="border-t hover:bg-muted/40">
                    <td className="px-3 py-2 font-mono text-xs">{c.id}</td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        {c.reporterName}
                        {c.anonymous && <Badge variant="secondary" className="text-[10px]">anon</Badge>}
                      </div>
                    </td>
                    <td className="px-3 py-2">{c.faculty}</td>
                    <td className="px-3 py-2 capitalize">{c.category}</td>
                    <td className="px-3 py-2 text-muted-foreground">{new Date(c.date).toLocaleDateString()}</td>
                    <td className="px-3 py-2">{c.assignedOfficer}</td>
                    <td className="px-3 py-2"><StatusBadge status={c.status} /></td>
                    <td className="px-3 py-2 text-right">
                      <Button asChild size="sm" variant="ghost">
                        <Link to="/dashboard/cases/$id" params={{ id: c.id }}>
                          <Eye className="mr-1 h-3.5 w-3.5" /> Detail
                        </Link>
                      </Button>
                    </td>
                  </tr>
                ))}
                {pageData.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-3 py-12 text-center text-sm text-muted-foreground">
                      No cases match your filters.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
              Showing {pageData.length} of {filtered.length} cases
            </span>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </Button>
              <span className="px-2">
                Page {page} of {totalPages}
              </span>
              <Button variant="outline" size="sm" disabled={page === totalPages} onClick={() => setPage((p) => p + 1)}>
                Next
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
