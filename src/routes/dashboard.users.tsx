import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { Plus, UserCheck, UserX } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { mockUsers } from "@/mock-data";
import type { AppUser } from "@/types";
import { toast } from "sonner";

export const Route = createFileRoute("/dashboard/users")({
  component: UsersPage,
  head: () => ({ meta: [{ title: "Users — SafeCampus Admin" }] }),
});

const ROLES: AppUser["role"][] = ["Super Admin", "Satgas Officer", "Reviewer", "Counselor"];

function UsersPage() {
  const [users, setUsers] = useState<AppUser[]>(mockUsers);
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<AppUser>({
    id: "",
    name: "",
    email: "",
    role: "Satgas Officer",
    active: true,
    lastActive: new Date().toISOString().slice(0, 10),
  });

  const add = () => {
    if (!draft.name || !draft.email) return toast.error("Name and email required");
    setUsers((p) => [{ ...draft, id: `u-${Date.now()}` }, ...p]);
    setOpen(false);
    setDraft({ id: "", name: "", email: "", role: "Satgas Officer", active: true, lastActive: new Date().toISOString().slice(0, 10) });
    toast.success("User added");
  };

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">User management</h1>
          <p className="text-sm text-muted-foreground">Manage admin accounts, roles, and access.</p>
        </div>
        <Button onClick={() => setOpen(true)}>
          <Plus className="mr-2 h-4 w-4" /> Add user
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Team members</CardTitle>
          <CardDescription>{users.length} accounts</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="px-3 py-2 text-left">Member</th>
                  <th className="px-3 py-2 text-left">Role</th>
                  <th className="px-3 py-2 text-left">Last active</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-t">
                    <td className="px-3 py-3">
                      <div className="flex items-center gap-3">
                        <Avatar className="h-8 w-8">
                          <AvatarFallback className="bg-primary/10 text-primary text-xs">
                            {u.name.split(" ").map((p) => p[0]).slice(0, 2).join("")}
                          </AvatarFallback>
                        </Avatar>
                        <div>
                          <div className="font-medium">{u.name}</div>
                          <div className="text-xs text-muted-foreground">{u.email}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-3 py-3">
                      <Select
                        value={u.role}
                        onValueChange={(v) => setUsers((p) => p.map((x) => (x.id === u.id ? { ...x, role: v as AppUser["role"] } : x)))}
                      >
                        <SelectTrigger className="h-8 w-[160px]"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          {ROLES.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}
                        </SelectContent>
                      </Select>
                    </td>
                    <td className="px-3 py-3 text-muted-foreground">{u.lastActive}</td>
                    <td className="px-3 py-3">
                      {u.active ? (
                        <Badge className="bg-success/15 text-success border-success/30" variant="outline">Active</Badge>
                      ) : (
                        <Badge variant="outline" className="text-muted-foreground">Disabled</Badge>
                      )}
                    </td>
                    <td className="px-3 py-3 text-right">
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setUsers((p) => p.map((x) => (x.id === u.id ? { ...x, active: !x.active } : x)))}
                      >
                        {u.active ? (
                          <><UserX className="mr-1 h-4 w-4" /> Disable</>
                        ) : (
                          <><UserCheck className="mr-1 h-4 w-4" /> Enable</>
                        )}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Add admin user</DialogTitle></DialogHeader>
          <div className="grid gap-4">
            <div className="grid gap-2">
              <Label>Name</Label>
              <Input value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
            </div>
            <div className="grid gap-2">
              <Label>Email</Label>
              <Input type="email" value={draft.email} onChange={(e) => setDraft({ ...draft, email: e.target.value })} />
            </div>
            <div className="grid gap-2">
              <Label>Role</Label>
              <Select value={draft.role} onValueChange={(v) => setDraft({ ...draft, role: v as AppUser["role"] })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {ROLES.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
            <Button onClick={add}>Add user</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
