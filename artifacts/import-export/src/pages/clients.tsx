import { useState } from "react";
import { useListClients, useCreateClient, useDeleteClient, useGetClientPaiements } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { getListClientsQueryKey } from "@workspace/api-client-react";
import { Button, Input, Modal, Label, Table, TableHeader, TableRow, TableHead, TableBody, TableCell, Card, Badge } from "@/components/ui";
import { formatFCFA, formatDate } from "@/lib/utils";
import { Plus, Trash2, Loader2, Search, History, Phone } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";

const clientSchema = z.object({
  nom: z.string().min(2, "Le nom est requis"),
  telephone: z.string().min(8, "Numéro valide requis"),
  adresse: z.string().optional()
});

type ClientForm = z.infer<typeof clientSchema>;

function HistoriquePaiements({ clientId }: { clientId: number }) {
  const { data, isLoading } = useGetClientPaiements(clientId, { query: { enabled: !!clientId } });

  if (isLoading) return <div className="p-8 flex justify-center"><Loader2 className="animate-spin" /></div>;
  if (!data || data.length === 0) return <div className="p-8 text-center text-slate-500">Aucun paiement trouvé pour ce client.</div>;

  return (
    <div className="space-y-4 pt-4">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Date</TableHead>
            <TableHead>Colis</TableHead>
            <TableHead>Mode</TableHead>
            <TableHead className="text-right">Montant payé</TableHead>
            <TableHead className="text-right">Reste à payer</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {data.map(p => (
            <TableRow key={p.id}>
              <TableCell>{formatDate(p.datePaiement)}</TableCell>
              <TableCell className="font-medium text-primary">{p.colisCode}</TableCell>
              <TableCell><Badge variant="outline" className="capitalize">{p.mode}</Badge></TableCell>
              <TableCell className="text-right font-medium text-emerald-600">+{formatFCFA(p.montant)}</TableCell>
              <TableCell className="text-right text-red-500">{formatFCFA(p.soldeApres)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

export default function Clients() {
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = useState("");
  // On passe le searchTerm directement à useListClients
  const { data: clients, isLoading } = useListClients({ search: searchTerm.length > 2 ? searchTerm : undefined });
  const createMutation = useCreateClient();
  const deleteMutation = useDeleteClient();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [historyClientId, setHistoryClientId] = useState<number | null>(null);

  const { register, handleSubmit, reset, formState: { errors } } = useForm<ClientForm>({
    resolver: zodResolver(clientSchema)
  });

  const onSubmit = (data: ClientForm) => {
    createMutation.mutate({ data }, {
      onSuccess: () => {
        toast.success("Client ajouté");
        queryClient.invalidateQueries({ queryKey: getListClientsQueryKey() });
        setIsCreateOpen(false);
        reset();
      },
      onError: () => toast.error("Erreur")
    });
  };

  const handleDelete = (id: number) => {
    if (confirm("Supprimer ce client ? Impossible s'il a des colis en cours.")) {
      deleteMutation.mutate({ id }, {
        onSuccess: () => {
          toast.success("Client supprimé");
          queryClient.invalidateQueries({ queryKey: getListClientsQueryKey() });
        },
        onError: () => toast.error("Impossible de supprimer (le client a probablement des dépendances)")
      });
    }
  };

  // Filtrage local en plus si l'API ne le fait pas bien
  const displayClients = clients?.filter(c => 
    c.nom.toLowerCase().includes(searchTerm.toLowerCase()) || 
    c.telephone.includes(searchTerm)
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-display font-bold text-slate-900">Clients</h1>
          <p className="text-slate-500 mt-1">Gérez votre base de clients et leurs soldes</p>
        </div>
        <Button onClick={() => setIsCreateOpen(true)}>
          <Plus className="mr-2 h-4 w-4" /> Nouveau client
        </Button>
      </div>

      <Card className="overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex items-center bg-white">
          <div className="relative w-full max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input 
              placeholder="Rechercher par nom ou téléphone..." 
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
                <TableHead>Nom complet</TableHead>
                <TableHead>Contact</TableHead>
                <TableHead>Inscrit le</TableHead>
                <TableHead className="text-right">Total Facturé</TableHead>
                <TableHead className="text-right">Solde Actuel</TableHead>
                <TableHead className="w-[120px]"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {displayClients?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 text-slate-500">Aucun client trouvé</TableCell>
                </TableRow>
              ) : (
                displayClients?.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-semibold text-slate-900">{c.nom}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2 text-slate-600">
                        <Phone className="h-3 w-3" /> {c.telephone}
                      </div>
                    </TableCell>
                    <TableCell className="text-slate-500">{formatDate(c.dateCreation)}</TableCell>
                    <TableCell className="text-right">{formatFCFA(c.totalDu)}</TableCell>
                    <TableCell className="text-right">
                      <span className={cn("font-bold", c.solde < 0 ? "text-red-600" : "text-emerald-600")}>
                        {formatFCFA(c.solde)}
                      </span>
                    </TableCell>
                    <TableCell className="text-right space-x-1">
                      <Button variant="ghost" size="icon" title="Historique paiements" onClick={() => setHistoryClientId(c.id)}>
                        <History className="h-4 w-4 text-primary" />
                      </Button>
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

      <Modal isOpen={isCreateOpen} onClose={() => setIsCreateOpen(false)} title="Nouveau client">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-4">
          <div className="space-y-2">
            <Label>Nom complet</Label>
            <Input {...register("nom")} placeholder="Ex: Jean Dupont" />
            {errors.nom && <span className="text-xs text-red-500">{errors.nom.message}</span>}
          </div>
          
          <div className="space-y-2">
            <Label>Téléphone</Label>
            <Input {...register("telephone")} placeholder="Ex: 01 02 03 04" />
            {errors.telephone && <span className="text-xs text-red-500">{errors.telephone.message}</span>}
          </div>

          <div className="space-y-2">
            <Label>Adresse (Optionnel)</Label>
            <Input {...register("adresse")} placeholder="Ex: Quartier X, Ville" />
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>Annuler</Button>
            <Button type="submit" isLoading={createMutation.isPending}>Ajouter</Button>
          </div>
        </form>
      </Modal>

      <Modal 
        isOpen={!!historyClientId} 
        onClose={() => setHistoryClientId(null)} 
        title="Historique des paiements" 
        maxWidth="max-w-3xl"
      >
        {historyClientId && <HistoriquePaiements clientId={historyClientId} />}
      </Modal>
    </div>
  );
}
