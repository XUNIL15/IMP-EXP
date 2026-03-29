import { useState } from "react";
import { useListArrivages, useCreateArrivage, useDeleteArrivage } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { getListArrivagesQueryKey } from "@workspace/api-client-react";
import { Button, Input, Modal, Label, Table, TableHeader, TableRow, TableHead, TableBody, TableCell, Card } from "@/components/ui";
import { formatFCFA, formatWeight, formatDate } from "@/lib/utils";
import { Plus, Trash2, Loader2, Search } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";

const arrivageSchema = z.object({
  dateArrivee: z.string().min(1, "La date est requise"),
  nbColisTotal: z.coerce.number().min(1, "Requis"),
  poidsTotal: z.coerce.number().min(0.1, "Requis"),
  coutTotal: z.coerce.number().min(0, "Requis"),
});

type ArrivageForm = z.infer<typeof arrivageSchema>;

export default function Arrivages() {
  const queryClient = useQueryClient();
  const { data: arrivages, isLoading } = useListArrivages();
  const createMutation = useCreateArrivage();
  const deleteMutation = useDeleteArrivage();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");

  const { register, handleSubmit, reset, formState: { errors } } = useForm<ArrivageForm>({
    resolver: zodResolver(arrivageSchema),
    defaultValues: {
      dateArrivee: new Date().toISOString().split('T')[0]
    }
  });

  const onSubmit = (data: ArrivageForm) => {
    createMutation.mutate({ data }, {
      onSuccess: () => {
        toast.success("Arrivage créé avec succès");
        queryClient.invalidateQueries({ queryKey: getListArrivagesQueryKey() });
        setIsModalOpen(false);
        reset();
      },
      onError: () => toast.error("Erreur lors de la création")
    });
  };

  const handleDelete = (id: number) => {
    if (confirm("Êtes-vous sûr de vouloir supprimer cet arrivage ? Les colis associés seront peut-être affectés.")) {
      deleteMutation.mutate({ id }, {
        onSuccess: () => {
          toast.success("Arrivage supprimé");
          queryClient.invalidateQueries({ queryKey: getListArrivagesQueryKey() });
        },
        onError: () => toast.error("Impossible de supprimer l'arrivage")
      });
    }
  };

  const filteredArrivages = arrivages?.filter(a => 
    formatDate(a.dateArrivee).toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-display font-bold text-slate-900">Arrivages</h1>
          <p className="text-slate-500 mt-1">Gérez vos réceptions de marchandises</p>
        </div>
        <Button onClick={() => setIsModalOpen(true)}>
          <Plus className="mr-2 h-4 w-4" /> Nouvel arrivage
        </Button>
      </div>

      <Card className="overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex items-center bg-white">
          <div className="relative w-full max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input 
              placeholder="Rechercher par date..." 
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
                <TableHead>ID</TableHead>
                <TableHead>Date d'arrivée</TableHead>
                <TableHead className="text-right">Nb Colis</TableHead>
                <TableHead className="text-right">Poids Total</TableHead>
                <TableHead className="text-right">Coût Total</TableHead>
                <TableHead className="w-[100px]"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredArrivages?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 text-slate-500">Aucun arrivage trouvé</TableCell>
                </TableRow>
              ) : (
                filteredArrivages?.map((arrivage) => (
                  <TableRow key={arrivage.id}>
                    <TableCell className="font-medium">#{arrivage.id}</TableCell>
                    <TableCell>{formatDate(arrivage.dateArrivee)}</TableCell>
                    <TableCell className="text-right">{arrivage.nbColisTotal}</TableCell>
                    <TableCell className="text-right">{formatWeight(arrivage.poidsTotal)}</TableCell>
                    <TableCell className="text-right font-medium">{formatFCFA(arrivage.coutTotal)}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon" onClick={() => handleDelete(arrivage.id)} className="text-red-500 hover:text-red-700 hover:bg-red-50">
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

      <Modal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} title="Enregistrer un arrivage">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-4">
          <div className="space-y-2">
            <Label>Date d'arrivée</Label>
            <Input type="date" {...register("dateArrivee")} />
            {errors.dateArrivee && <span className="text-xs text-red-500">{errors.dateArrivee.message}</span>}
          </div>
          
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Nombre de colis</Label>
              <Input type="number" {...register("nbColisTotal")} />
              {errors.nbColisTotal && <span className="text-xs text-red-500">{errors.nbColisTotal.message}</span>}
            </div>
            <div className="space-y-2">
              <Label>Poids total (kg)</Label>
              <Input type="number" step="0.1" {...register("poidsTotal")} />
              {errors.poidsTotal && <span className="text-xs text-red-500">{errors.poidsTotal.message}</span>}
            </div>
          </div>

          <div className="space-y-2">
            <Label>Coût total de l'arrivage (FCFA)</Label>
            <Input type="number" {...register("coutTotal")} />
            {errors.coutTotal && <span className="text-xs text-red-500">{errors.coutTotal.message}</span>}
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>Annuler</Button>
            <Button type="submit" isLoading={createMutation.isPending}>Enregistrer</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
