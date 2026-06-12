import { Link } from "@tanstack/react-router";
import { ShieldAlert } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

interface AccessDeniedProps {
  backTo?: string;
  backLabel?: string;
}

export function AccessDenied({
  backTo = "/dashboard",
  backLabel = "Back to dashboard",
}: AccessDeniedProps) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4">
      <Card className="w-full max-w-md">
        <CardContent className="p-8 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
            <ShieldAlert className="h-6 w-6" />
          </div>
          <h1 className="mt-5 text-xl font-semibold">Access denied</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            Your account is authenticated, but it does not have access to this
            area.
          </p>
          <Button asChild className="mt-6">
            <Link to={backTo}>{backLabel}</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}

