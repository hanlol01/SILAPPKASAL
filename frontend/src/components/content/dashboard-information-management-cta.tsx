import { Link } from "@tanstack/react-router";
import { ArrowRight, FilePlus2, ShieldCheck } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";

export function DashboardInformationManagementCta() {
  const { roleCode, user } = useAuth();
  const permissions = new Set(user?.permissions ?? []);
  const campusManager =
    roleCode === "admin" && permissions.has("content.read.management.own_campus");
  const globalManager =
    roleCode === "super_admin" && permissions.has("content.read.management.all");
  if (!campusManager && !globalManager) return null;
  const superAdmin = globalManager;

  return (
    <Card className="border-primary/20 bg-primary/5">
      <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            {superAdmin ? <ShieldCheck className="h-5 w-5" /> : <FilePlus2 className="h-5 w-5" />}
          </span>
          <div>
            <p className="font-semibold">{superAdmin ? "Tinjau tata kelola konten" : "Kelola konten kampus"}</p>
            <p className="mt-1 text-sm text-muted-foreground">
              {superAdmin ? "Buka antrean review, publikasi, dan Sorotan Edukasi." : "Buat dan perbarui informasi untuk kampus Anda."}
            </p>
          </div>
        </div>
        <Button asChild className="min-h-11 shrink-0">
          <Link to={superAdmin ? "/dashboard/content-governance" : "/dashboard/content"}>
            {superAdmin ? "Buka governance" : "Kelola konten"}
            <ArrowRight className="h-4 w-4" />
          </Link>
        </Button>
      </CardContent>
    </Card>
  );
}
