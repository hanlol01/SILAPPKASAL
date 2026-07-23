import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, ChevronsUpDown, Loader2, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ApiError } from "@/lib/api-client";
import {
  contentManagementKeys,
  createManagedArticleCategory,
  deactivateManagedArticleCategory,
  getManagedArticleCategories,
  type ManagedArticleCategory,
} from "@/lib/content-management-api";
import { cn } from "@/lib/utils";

interface Props {
  section: "education" | "policy" | "all";
  value: string;
  onValueChange: (value: string) => void;
  disabled?: boolean;
  allowCreate?: boolean;
  allowManage?: boolean;
  allowClear?: boolean;
  placeholder?: string;
  onBlur?: () => void;
}

export function ContentCategoryCombobox({
  section,
  value,
  onValueChange,
  disabled = false,
  allowCreate = false,
  allowManage = false,
  allowClear = false,
  placeholder = "Pilih atau cari kategori",
  onBlur,
}: Props) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [pendingDelete, setPendingDelete] = useState<ManagedArticleCategory | null>(null);
  const educationQuery = useQuery({
    queryKey: contentManagementKeys.articleCategories("education"),
    queryFn: () => getManagedArticleCategories("education"),
    enabled: section === "education" || section === "all",
  });
  const policyQuery = useQuery({
    queryKey: contentManagementKeys.articleCategories("policy"),
    queryFn: () => getManagedArticleCategories("policy"),
    enabled: section === "policy" || section === "all",
  });
  const categories = useMemo(() => {
    const source = [
      ...(section !== "policy" ? (educationQuery.data ?? []) : []),
      ...(section !== "education" ? (policyQuery.data ?? []) : []),
    ];
    return source.filter(
      (category, index) => source.findIndex((candidate) => candidate.name.toLocaleLowerCase() === category.name.toLocaleLowerCase()) === index,
    );
  }, [educationQuery.data, policyQuery.data, section]);
  const loading = educationQuery.isLoading || policyQuery.isLoading;

  const normalizedSearch = search.trim();
  const exactMatch = useMemo(
    () => categories.some((category) => category.name.localeCompare(normalizedSearch, undefined, { sensitivity: "accent" }) === 0),
    [categories, normalizedSearch],
  );
  const validNewName = normalizedSearch.length <= 100 && /[\p{L}\p{N}]/u.test(normalizedSearch);

  const createMutation = useMutation({
    mutationFn: () => {
      if (section === "all") throw new Error("A section is required to create a category.");
      return createManagedArticleCategory(section, normalizedSearch);
    },
    onSuccess: async (category) => {
      await queryClient.invalidateQueries({ queryKey: contentManagementKeys.articleCategories(section) });
      onValueChange(category.name);
      setSearch("");
      setOpen(false);
      toast.success(`Kategori “${category.name}” ditambahkan.`);
    },
    onError: (error) => toast.error(categoryErrorMessage(error, "Kategori gagal ditambahkan.")),
  });

  const deleteMutation = useMutation({
    mutationFn: (publicId: string) => deactivateManagedArticleCategory(publicId),
    onSuccess: async () => {
      const removedName = pendingDelete?.name;
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: contentManagementKeys.articleCategories("education") }),
        queryClient.invalidateQueries({ queryKey: contentManagementKeys.articleCategories("policy") }),
      ]);
      if (removedName && value === removedName) onValueChange("");
      setPendingDelete(null);
      toast.success("Kategori dinonaktifkan.");
    },
    onError: (error) => toast.error(categoryErrorMessage(error, "Kategori gagal dinonaktifkan.")),
  });

  return (
    <>
      <Popover
        open={open}
        onOpenChange={(nextOpen) => {
          setOpen(nextOpen);
          if (!nextOpen) {
            setSearch("");
            onBlur?.();
          }
        }}
      >
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            aria-label="Kategori artikel"
            disabled={disabled}
            className="h-11 w-full justify-between px-3 font-normal"
          >
            <span className={cn("truncate", !value && "text-muted-foreground")}>{value || placeholder}</span>
            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] p-0">
          <Command shouldFilter>
            <CommandInput
              value={search}
              onValueChange={setSearch}
              placeholder="Cari kategori..."
              maxLength={100}
            />
            <CommandList>
              <CommandEmpty>
                {loading ? "Memuat kategori..." : "Kategori tidak ditemukan."}
              </CommandEmpty>
              <CommandGroup>
                {allowClear && (
                  <CommandItem value="semua kategori" onSelect={() => { onValueChange(""); setOpen(false); }}>
                    <Check className={cn("h-4 w-4", value === "" ? "opacity-100" : "opacity-0")} />
                    <span>Semua kategori</span>
                  </CommandItem>
                )}
                {categories.map((category) => (
                  <CommandItem
                    key={`${category.scope}-${category.public_id ?? category.name}`}
                    value={category.name}
                    onSelect={() => {
                      onValueChange(category.name);
                      setOpen(false);
                    }}
                    className="min-h-10"
                  >
                    <Check className={cn("h-4 w-4", value === category.name ? "opacity-100" : "opacity-0")} />
                    <span className="min-w-0 flex-1 truncate">{category.name}</span>
                    {category.usage_count > 0 && (
                      <span className="text-xs text-muted-foreground">{category.usage_count} konten</span>
                    )}
                    {allowManage && category.can_manage && category.public_id && (
                      <button
                        type="button"
                        aria-label={`Hapus kategori ${category.name}`}
                        title={category.can_deactivate ? "Nonaktifkan kategori" : "Kategori masih digunakan"}
                        disabled={!category.can_deactivate}
                        className="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-35"
                        onClick={(event) => {
                          event.preventDefault();
                          event.stopPropagation();
                          if (category.can_deactivate) setPendingDelete(category);
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </CommandItem>
                ))}
                {allowCreate && section !== "all" && normalizedSearch && !exactMatch && (
                  <CommandItem
                    value={`create-${normalizedSearch}`}
                    disabled={!validNewName || createMutation.isPending}
                    onSelect={() => {
                      if (validNewName) createMutation.mutate();
                    }}
                  >
                    {createMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Plus className="h-4 w-4" />
                    )}
                    <span className="truncate">Tambah kategori “{normalizedSearch}”</span>
                  </CommandItem>
                )}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>

      <AlertDialog open={pendingDelete !== null} onOpenChange={(nextOpen) => !nextOpen && setPendingDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Nonaktifkan kategori “{pendingDelete?.name}”?</AlertDialogTitle>
            <AlertDialogDescription>
              Kategori akan hilang dari daftar pilihan baru. Konten yang sudah ada tidak akan diubah.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Batal</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleteMutation.isPending}
              onClick={(event) => {
                event.preventDefault();
                if (pendingDelete?.public_id) deleteMutation.mutate(pendingDelete.public_id);
              }}
            >
              {deleteMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Nonaktifkan kategori
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

function categoryErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    if (error.errorCode === "content_category_in_use") {
      return "Kategori masih digunakan dan tidak dapat dinonaktifkan.";
    }
    return error.message || fallback;
  }
  return fallback;
}
