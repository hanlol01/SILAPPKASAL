import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import {
  ArrowLeft,
  CheckCircle2,
  FileText,
  Image as ImageIcon,
  Lock,
  MapPin,
  MessageSquare,
  ShieldAlert,
  UserCog,
  XCircle,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { StatusBadge } from "@/components/status-badge";
import { mockCases } from "@/mock-data";
import { toast } from "sonner";

export const Route = createFileRoute("/dashboard/cases/$id")({
  component: CaseDetail,
});

function CaseDetail() {
  const { id } = Route.useParams();
  const navigate = useNavigate();
  const initial = mockCases.find((c) => c.id === id);
  const [c, setC] = useState(initial);
  const [note, setNote] = useState("");

  if (!c) {
    return (
      <div className="space-y-4">
        <Button variant="ghost" onClick={() => navigate({ to: "/dashboard/cases" })}>
          <ArrowLeft className="mr-2 h-4 w-4" /> Back
        </Button>
        <p className="text-sm text-muted-foreground">Case not found.</p>
      </div>
    );
  }

  const update = (action: string, status?: typeof c.status) => {
    const next = {
      ...c,
      status: status ?? c.status,
      timeline: [
        ...c.timeline,
        { date: new Date().toISOString(), actor: "You", action },
      ],
    };
    setC(next);
    toast.success(action);
  };

  const addNote = () => {
    if (!note.trim()) return;
    setC({
      ...c,
      notes: [{ author: "You", date: new Date().toISOString(), content: note.trim() }, ...c.notes],
    });
    setNote("");
    toast.success("Internal note added");
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/dashboard/cases">
            <ArrowLeft className="mr-2 h-4 w-4" /> All cases
          </Link>
        </Button>
        <div className="flex items-center gap-2">
          <h1 className="font-mono text-lg font-semibold">{c.id}</h1>
          <StatusBadge status={c.status} />
          {c.anonymous && (
            <Badge variant="secondary" className="gap-1">
              <Lock className="h-3 w-3" /> Anonymous
            </Badge>
          )}
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Reporter information</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
              <Field label="Reporter">{c.reporterName}</Field>
              <Field label="Faculty">{c.faculty}</Field>
              <Field label="Category"><span className="capitalize">{c.category}</span></Field>
              <Field label="Submitted">{new Date(c.date).toLocaleString()}</Field>
              <Field label="Assigned officer">{c.assignedOfficer}</Field>
              <Field label="Location">
                <span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" />{c.location}</span>
              </Field>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">Incident details</CardTitle></CardHeader>
            <CardContent className="text-sm leading-relaxed text-muted-foreground">
              {c.description}
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">Evidence</CardTitle></CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {c.evidence.map((e, i) => (
                  <div key={i} className="flex aspect-square flex-col items-center justify-center rounded-lg border bg-muted/40 text-xs text-muted-foreground">
                    {e.type === "image" ? <ImageIcon className="mb-2 h-6 w-6" /> : <FileText className="mb-2 h-6 w-6" />}
                    <span className="px-2 text-center">{e.name}</span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">Internal notes</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Textarea
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder="Add a confidential note for the team..."
                  rows={3}
                />
                <div className="flex justify-end">
                  <Button size="sm" onClick={addNote}>
                    <MessageSquare className="mr-2 h-4 w-4" /> Add note
                  </Button>
                </div>
              </div>
              <Separator />
              <div className="space-y-3">
                {c.notes.map((n, i) => (
                  <div key={i} className="flex gap-3">
                    <Avatar className="h-8 w-8">
                      <AvatarFallback className="bg-primary/10 text-primary text-xs">
                        {n.author.split(" ").map((p)=>p[0]).slice(0,2).join("")}
                      </AvatarFallback>
                    </Avatar>
                    <div className="flex-1 rounded-lg border bg-muted/30 p-3 text-sm">
                      <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">{n.author}</span>
                        <span>{new Date(n.date).toLocaleString()}</span>
                      </div>
                      {n.content}
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-base">Actions</CardTitle></CardHeader>
            <CardContent className="grid gap-2">
              <Button onClick={() => update("Report accepted", "verification")} variant="default">
                <CheckCircle2 className="mr-2 h-4 w-4" /> Accept report
              </Button>
              <Button onClick={() => update("Clarification requested")} variant="outline">
                <MessageSquare className="mr-2 h-4 w-4" /> Request clarification
              </Button>
              <Button onClick={() => update("Investigator assigned", "investigation")} variant="outline">
                <UserCog className="mr-2 h-4 w-4" /> Assign investigator
              </Button>
              <Button onClick={() => update("Moved to mediation", "mediation")} variant="outline">
                <ShieldAlert className="mr-2 h-4 w-4" /> Move to mediation
              </Button>
              <Button onClick={() => update("Marked as resolved", "resolved")} variant="outline">
                <CheckCircle2 className="mr-2 h-4 w-4" /> Mark resolved
              </Button>
              <Button onClick={() => update("Case closed", "closed")} variant="ghost" className="text-destructive">
                <XCircle className="mr-2 h-4 w-4" /> Close case
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">Timeline</CardTitle></CardHeader>
            <CardContent>
              <ol className="relative space-y-4 border-l border-border pl-4">
                {[...c.timeline].reverse().map((t, i) => (
                  <li key={i} className="relative">
                    <span className="absolute -left-[21px] top-1 h-3 w-3 rounded-full border-2 border-background bg-primary" />
                    <div className="text-sm">
                      <div className="font-medium">{t.action}</div>
                      <div className="text-xs text-muted-foreground">
                        {t.actor} · {new Date(t.date).toLocaleString()}
                      </div>
                    </div>
                  </li>
                ))}
              </ol>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 text-sm">{children}</div>
    </div>
  );
}
