import { useState } from "react";
import { useListDettes, useEnregistrerPaiement, useListClients } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { getListDettesQueryKey, getListClientsQueryKey, getGetDashboardQueryKey } from "@workspace/api-client-react";
import { Button, Input, Select, Modal, Label, Table, TableHeader, TableRow, TableHead, TableBody, TableCell, Card } from "@/components/ui";
import { formatFCFA, formatDate } from "@/lib/utils";
import { Loader2, Banknote, AlertCircle } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";

const paiementSchema = z.object({
  montant: z.coerce.number().min(1, "Montant invalide"),
  mode: z.enum(["especes", "virement", "cheque", "mobile"])
});

type PaiementForm = z.infer<typeof paiementSchema>;

export default function Dettes() {
  const queryClient = useQueryClient();
  const [selectedClientId, setSelectedClientId] = useState<number | undefined>(undefined);
  
  const { data: dettes, isLoading } = useListDettes({ clientId: selectedClientId });
  const { data: clients } = useListClients();
  const payMutation = useEnregistrerPaiement();

  const [paymentModalData, setPaymentModalData] = useState<{ id: number, code: string, max: number, client: string } | null>(null);

  const { register, handleSubmit, reset, formState: { errors } } = useForm<PaiementForm>({
    resolver: zodResolver(paiementSchema),
    defaultValues: { mode: "especes" }
  });

  const onPay = (data: PaiementForm) => {
    if (!paymentModalData) return;
    if (data.montant > paymentModalData.max) {
      toast.error(`Le montant ne peut dépasser le reste à payer (${formatFCFA(paymentModalData.max)})`);
      return;
    }

    payMutation.mutate({ id: paymentModalData.id, data }, {
      onSuccess: () => {
        toast.success("Paiement enregistré avec succès");
        queryClient.invalidateQueries({ queryKey: getListDettesQueryKey() });
        queryClient.invalidateQueries({ queryKey: getListClientsQueryKey() });
        queryClient.invalidateQueries({ queryKey: getGetDashboardQueryKey() });
        setPaymentModalData(null);
        reset();
      },
      onError: (err: any) => toast.error(err.message || "Erreur lors du paiement")
    });
  };

  const totalDettes = dettes?.reduce((sum, d) => sum + Math.abs(d.solde), 0) || 0;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-display font-bold text-slate-900">Dettes & Recouvrements</h1>
        <p className="text-slate-500 mt-1">Suivez les impayés et enregistrez les paiements</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <Card className="md:col-span-1 bg-red-50/50 border-red-100">
          <div className="p-6 flex items-center gap-4">
            <div className="h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
              <AlertCircle className="h-6 w-6 text-red-600" />
            </div>
            <div>
              <p className="text-sm font-medium text-red-600 uppercase tracking-wide">Total Dettes Affichées</p>
              <p className="text-3xl font-bold text-red-700">{formatFCFA(totalDettes)}</p>
            </div>
          </div>
        </Card>

        <Card className="md:col-span-2">
          <div className="p-6 flex items-center h-full gap-4">
            <div className="flex-1 space-y-2">
              <Label>Filtrer par client</Label>
              <Select 
                value={selectedClientId || ""} 
                onChange={(e) => setSelectedClientId(e.target.value ? Number(e.target.value) : undefined)}
              >
                <option value="">Tous les clients débiteurs</option>
                {clients?.map(c => (
                  <option key={c.id} value={c.id}>{c.nom}</option>
                ))}
              </Select>
            </div>
          </div>
        </Card>
      </div>

      <Card>
        {isLoading ? (
          <div className="flex justify-center p-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Client</TableHead>
                <TableHead>Téléphone</TableHead>
                <TableHead>Colis</TableHead>
                <TableHead>Date Arrivage</TableHead>
                <TableHead className="text-right">Montant Total</TableHead>
                <TableHead className="text-right">Déjà Payé</TableHead>
                <TableHead className="text-right text-red-600">Reste à payer</TableHead>
                <TableHead className="w-[140px] text-center">Action</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {dettes?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center py-12 text-slate-500 text-lg">
                    Aucune dette trouvée. Tout est à jour ! 🎉
                  </TableCell>
                </TableRow>
              ) : (
                dettes?.map((d) => (
                  <TableRow key={d.colisProprietaireId}>
                    <TableCell className="font-bold text-slate-900">{d.clientNom}</TableCell>
                    <TableCell className="text-slate-500">{d.clientTelephone}</TableCell>
                    <TableCell className="font-medium text-primary">{d.colisCode}</TableCell>
                    <TableCell>{formatDate(d.arrivageDate)}</TableCell>
                    <TableCell className="text-right">{formatFCFA(d.montantDu)}</TableCell>
                    <TableCell className="text-right text-emerald-600">{formatFCFA(d.montantPaye)}</TableCell>
                    <TableCell className="text-right font-bold text-red-600">{formatFCFA(Math.abs(d.solde))}</TableCell>
                    <TableCell className="text-center">
                      <Button 
                        size="sm" 
                        className="w-full bg-emerald-600 hover:bg-emerald-700"
                        onClick={() => setPaymentModalData({ 
                          id: d.colisProprietaireId, 
                          code: d.colisCode, 
                          max: Math.abs(d.solde),
                          client: d.clientNom 
                        })}
                      >
                        <Banknote className="mr-1.5 h-4 w-4" /> Payer
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        )}
      </Card>

      <Modal 
        isOpen={!!paymentModalData} 
        onClose={() => { setPaymentModalData(null); reset(); }} 
        title={`Encaisser paiement - ${paymentModalData?.client}`}
      >
        <form onSubmit={handleSubmit(onPay)} className="space-y-4 pt-4">
          <div className="bg-slate-50 p-4 rounded-lg border border-slate-100 mb-4 text-sm">
            <p>Règlement pour le colis : <strong className="text-primary">{paymentModalData?.code}</strong></p>
            <p>Reste à payer : <strong className="text-red-600 text-lg">{formatFCFA(paymentModalData?.max || 0)}</strong></p>
          </div>

          <div className="space-y-2">
            <Label>Montant à encaisser (FCFA)</Label>
            <Input type="number" {...register("montant")} placeholder="Ex: 50000" autoFocus />
            {errors.montant && <span className="text-xs text-red-500">{errors.montant.message}</span>}
          </div>

          <div className="space-y-2">
            <Label>Mode de paiement</Label>
            <Select {...register("mode")}>
              <option value="especes">Espèces</option>
              <option value="mobile">Mobile Money</option>
              <option value="virement">Virement Bancaire</option>
              <option value="cheque">Chèque</option>
            </Select>
            {errors.mode && <span className="text-xs text-red-500">{errors.mode.message}</span>}
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <Button type="button" variant="outline" onClick={() => setPaymentModalData(null)}>Annuler</Button>
            <Button type="submit" isLoading={payMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700">Valider</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
