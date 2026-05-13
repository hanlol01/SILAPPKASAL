import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Plus, Pencil, Trash2, BookOpen } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { mockArticles } from "@/mock-data";
import type { Article } from "@/types";
import { toast } from "sonner";

export const Route = createFileRoute("/dashboard/content")({
  component: ContentPage,
  head: () => ({ meta: [{ title: "Content — SafeCampus Admin" }] }),
});

const STORAGE_KEY = "safecampus_articles";
const CATEGORIES = ["Prevention", "Sexual education", "Counseling", "Campus policy", "Awareness campaign"];

function ContentPage() {
  const [articles, setArticles] = useState<Article[]>(mockArticles);
  const [editing, setEditing] = useState<Article | null>(null);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const raw = typeof window !== "undefined" ? localStorage.getItem(STORAGE_KEY) : null;
    if (raw) setArticles(JSON.parse(raw));
  }, []);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(articles));
  }, [articles]);

  const startNew = () => {
    setEditing({
      id: `art-${Date.now()}`,
      title: "",
      category: CATEGORIES[0],
      thumbnail: "",
      content: "",
      author: "You",
      publishDate: new Date().toISOString().slice(0, 10),
      published: false,
    });
    setOpen(true);
  };

  const save = () => {
    if (!editing) return;
    if (!editing.title.trim()) {
      toast.error("Title is required");
      return;
    }
    setArticles((prev) => {
      const exists = prev.some((a) => a.id === editing.id);
      return exists ? prev.map((a) => (a.id === editing.id ? editing : a)) : [editing, ...prev];
    });
    setOpen(false);
    setEditing(null);
    toast.success("Article saved");
  };

  const remove = (id: string) => {
    setArticles((prev) => prev.filter((a) => a.id !== id));
    toast.success("Article deleted");
  };

  const togglePublish = (id: string) => {
    setArticles((prev) =>
      prev.map((a) => (a.id === id ? { ...a, published: !a.published } : a)),
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Educational content</h1>
          <p className="text-sm text-muted-foreground">
            Publish prevention, awareness, and policy articles for the campus community.
          </p>
        </div>
        <Button onClick={startNew}>
          <Plus className="mr-2 h-4 w-4" /> New article
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {articles.map((a) => (
          <Card key={a.id} className="flex flex-col overflow-hidden">
            <div className="flex h-32 items-center justify-center bg-gradient-to-br from-primary/10 via-accent/30 to-primary/5 text-primary">
              <BookOpen className="h-10 w-10 opacity-70" />
            </div>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Badge variant="secondary">{a.category}</Badge>
                {a.published ? (
                  <Badge className="bg-success/15 text-success border-success/30" variant="outline">Published</Badge>
                ) : (
                  <Badge variant="outline">Draft</Badge>
                )}
              </div>
              <CardTitle className="line-clamp-2 text-base">{a.title}</CardTitle>
              <CardDescription className="line-clamp-2">{a.content}</CardDescription>
            </CardHeader>
            <CardContent className="mt-auto space-y-3 text-xs text-muted-foreground">
              <div>{a.author} · {new Date(a.publishDate).toLocaleDateString()}</div>
              <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 text-xs">
                  <Switch checked={a.published} onCheckedChange={() => togglePublish(a.id)} />
                  {a.published ? "Live" : "Hidden"}
                </label>
                <div className="flex gap-1">
                  <Button size="icon" variant="ghost" onClick={() => { setEditing(a); setOpen(true); }}>
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button size="icon" variant="ghost" className="text-destructive">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Delete article?</AlertDialogTitle>
                        <AlertDialogDescription>
                          This will permanently remove "{a.title}" from local storage.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={() => remove(a.id)}>Delete</AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing && articles.some((a) => a.id === editing.id) ? "Edit article" : "New article"}</DialogTitle>
            <DialogDescription>Articles are stored locally in your browser.</DialogDescription>
          </DialogHeader>
          {editing && (
            <div className="grid gap-4">
              <div className="grid gap-2">
                <Label htmlFor="title">Title</Label>
                <Input
                  id="title"
                  value={editing.title}
                  onChange={(e) => setEditing({ ...editing, title: e.target.value })}
                />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label>Category</Label>
                  <Select value={editing.category} onValueChange={(v) => setEditing({ ...editing, category: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {CATEGORIES.map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="author">Author</Label>
                  <Input
                    id="author"
                    value={editing.author}
                    onChange={(e) => setEditing({ ...editing, author: e.target.value })}
                  />
                </div>
              </div>
              <div className="grid gap-2">
                <Label htmlFor="content">Content</Label>
                <Textarea
                  id="content"
                  rows={6}
                  value={editing.content}
                  onChange={(e) => setEditing({ ...editing, content: e.target.value })}
                />
              </div>
              <label className="flex items-center gap-2 text-sm">
                <Switch
                  checked={editing.published}
                  onCheckedChange={(v) => setEditing({ ...editing, published: Boolean(v) })}
                />
                Publish immediately
              </label>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
            <Button onClick={save}>Save article</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
