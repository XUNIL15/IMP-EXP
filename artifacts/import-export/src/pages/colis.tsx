import { useState, useEffect } from "react";
import { useListColis, useCreateColis, useDeleteColis, useListArrivages, useListClients } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { getListColisQueryKey } from "@workspace/api-client-react";
import { Button, Input, Select, Modal, Label, Table, TableHeader, TableRow, TableHead, TableBody, TableCell, Card, Badge } from "@/components/ui";
import { formatFCFA, formatWeight, formatDate } from "@/lib/utils";
import { Plus, Trash2, Loader2, Users, Search, X } from "lucide-react";
import { useForm, useFieldArray } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";

const colisSchema = z.object({
  arrivageId: z.coerce.number().min(1, "Requis"),
  codeColisReel: z.string().min(1, "Requis"),
  type: z.enum(["individuel", "mixte"]),
  poids: z.coerce.number().min(0.1, "Requis"),
  montant: z.coerce.number().min(0, "Requis"),
  proprietaires: z.array(z.object({
    clientId: z.coerce.number().min(1, "Requis"),
    poids: z.coerce.number().min(0.1, "Requis"),
    montantDu: z.coerce.number().min(0, "Requis"),
    montantPaye: z.coerce.number().min(0, "Requis"),
  })).min(1, "Au moins un propriétaire")
});

type ColisForm = z.infer<typeof colisSchema>;

export default function Colis() {
  const queryClient = useQueryClient();
  const { data: colis, isLoading } = useListColis({});
  const { data: arrivages } = useListArrivages();
  const { data: clients } = useListClients();
  const createMutation = useCreateColis();
  const deleteMutation = useDeleteColis();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");

  const { register, control, handleSubmit, watch, setValue, reset, formState: { errors } } = useForm<ColisForm>({
    resolver: zodResolver(colisSchema),
    defaultValues: {
      type: "individuel",
      proprietaires: [{ clientId: 0, poids: 0, montantDu: 0, montantPaye: 0 }]
    }
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "proprietaires"
  });

  const typeWatch = watch("type");
  const montantWatch = watch("montant");
  const poidsWatch = watch("poids");

  // Auto-fill pour colis individuel
  useEffect(() => {
    if (typeWatch === "individuel" && fields.length > 0) {
      setValue("proprietaires.0.poids", poidsWatch || 0);
      setValue("proprietaires.0.montantDu", montantWatch || 0);
    }
  }, [typeWatch, poidsWatch, montantWatch]);

  const onSubmit = (data: ColisForm) => {
    createMutation.mutate({ data }, {
      onSuccess: () => {
        toast.success("Colis ajouté avec succès");
        queryClient.invalidateQueries({ queryKey: getListColisQueryKey() });
        setIsModalOpen(false);
        reset();
      },
      onError: (err: any) => toast.error(err.message || "Erreur lors de la création")
    });
  };

  const handleDelete = (id: number) => {
    if (confirm("Supprimer ce colis ?")) {
      deleteMutation.mutate({ id }, {
        onSuccess: () => {
          toast.success("Colis supprimé");
          queryClient.invalidateQueries({ queryKey: getListColisQueryKey() });
        },
        onError: () => toast.error("Impossible de supprimer")
      });
    }
  };

  const filteredColis = colis?.filter(c => 
    c.codeColisComplet.toLowerCase().includes(searchTerm.toLowerCase()) ||
    c.proprietaires.some(p => p.clientNom.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-display font-bold text-slate-900">Colis</h1>
          <p className="text-slate-500 mt-1">Gestion détaillée des colis et propriétaires</p>
        </div>
        <Button onClick={() => { reset(); setIsModalOpen(true); }}>
          <Plus className="mr-2 h-4 w-4" /> Ajouter un colis
        </Button>
      </div>

      <Card className="overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex gap-4 bg-white items-center">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input 
              placeholder="Rechercher par code ou client..." 
              className="pl-9 bg-slate-50"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        {isLoading ? (
          <div className="flex justify-center p-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Code Complet</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Propriétaires</TableHead>
                <TableHead className="text-right">Poids</TableHead>
                <TableHead className="text-right">Montant Total</TableHead>
                <TableHead>Arrivage</TableHead>
                <TableHead className="w-[80px]"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredColis?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 text-slate-500">Aucun colis trouvé</TableCell>
                </TableRow>
              ) : (
                filteredColis?.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-bold text-primary">{c.codeColisComplet}</TableCell>
                    <TableCell>
                      <Badge variant={c.type === 'individuel' ? 'default' : 'warning'}>
                        {c.type === 'individuel' ? 'Individuel' : 'Mixte'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {c.type === 'individuel' ? (
                        <span className="font-medium">{c.proprietaires[0]?.clientNom}</span>
                      ) : (
                        <div className="flex flex-col text-xs">
                          <span className="font-medium text-slate-700">{c.proprietaires.length} clients</span>
                          <span className="text-slate-500">{c.proprietaires.map(p => p.clientNom).join(', ').substring(0, 30)}...</span>
                        </div>
                      )}
                    </TableCell>
                    <TableCell className="text-right">{formatWeight(c.poids)}</TableCell>
                    <TableCell className="text-right font-medium">{formatFCFA(c.montant)}</TableCell>
                    <TableCell className="text-slate-500">{formatDate(c.arrivageDate)}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon" onClick={() => handleDelete(c.id)} className="text-red-500 hover:text-red-700">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        )}
      </Card>

      <Modal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} title="Ajouter un colis" maxWidth="max-w-2xl">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 pt-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Arrivage de rattachement</Label>
              <Select {...register("arrivageId")}>
                <option value={0}>Sélectionnez un arrivage</option>
                {arrivages?.map(a => (
                  <option key={a.id} value={a.id}>Arrivage du {formatDate(a.dateArrivee)}</option>
                ))}
              </Select>
              {errors.arrivageId && <span className="text-xs text-red-500">{errors.arrivageId.message}</span>}
            </div>
            
            <div className="space-y-2">
              <Label>Code ID (Ex: A109)</Label>
              <Input placeholder="A109" {...register("codeColisReel")} />
              {errors.codeColisReel && <span className="text-xs text-red-500">{errors.codeColisReel.message}</span>}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label>Type de colis</Label>
              <Select {...register("type")}>
                <option value="individuel">Individuel (1 client)</option>
                <option value="mixte">Mixte (Plusieurs clients)</option>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Poids total (kg)</Label>
              <Input type="number" step="0.1" {...register("poids")} />
            </div>
            <div className="space-y-2">
              <Label>Montant total (FCFA)</Label>
              <Input type="number" {...register("montant")} />
            </div>
          </div>

          <div className="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-4">
            <div className="flex items-center justify-between">
              <h4 className="font-semibold text-slate-800 flex items-center"><Users className="mr-2 h-4 w-4"/> Propriétaire(s)</h4>
              {typeWatch === "mixte" && (
                <Button type="button" variant="outline" size="sm" onClick={() => append({ clientId: 0, poids: 0, montantDu: 0, montantPaye: 0 })}>
                  <Plus className="h-3 w-3 mr-1" /> Ajouter
                </Button>
              )}
            </div>

            {fields.map((field, index) => (
              <div key={field.id} className="flex items-end gap-3 pb-4 border-b border-slate-200 last:border-0 last:pb-0">
                <div className="flex-1 space-y-2">
                  <Label className="text-xs">Client</Label>
                  <Select {...register(`proprietaires.${index}.clientId` as const)}>
                    <option value={0}>Sélectionnez</option>
                    {clients?.map(c => <option key={c.id} value={c.id}>{c.nom}</option>)}
                  </Select>
                </div>
                
                {typeWatch === "mixte" && (
                  <>
                    <div className="w-24 space-y-2">
                      <Label className="text-xs">Poids (kg)</Label>
                      <Input type="number" step="0.1" {...register(`proprietaires.${index}.poids` as const)} />
                    </div>
                    <div className="w-32 space-y-2">
                      <Label className="text-xs">À payer (FCFA)</Label>
                      <Input type="number" {...register(`proprietaires.${index}.montantDu` as const)} />
                    </div>
                  </>
                )}

                <div className="w-32 space-y-2">
                  <Label className="text-xs">Avance (FCFA)</Label>
                  <Input type="number" {...register(`proprietaires.${index}.montantPaye` as const)} />
                </div>

                {typeWatch === "mixte" && fields.length > 1 && (
                  <Button type="button" variant="ghost" size="icon" className="text-red-500 mb-0.5" onClick={() => remove(index)}>
                    <X className="h-4 w-4" />
                  </Button>
                )}
              </div>
            ))}
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="ghost" onClick={() => setIsModalOpen(false)}>Annuler</Button>
            <Button type="submit" isLoading={createMutation.isPending}>Enregistrer le colis</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
