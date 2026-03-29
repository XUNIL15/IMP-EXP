import { useState } from "react";
import {
  useListArrivages, useCreateArrivage, useDeleteArrivage, useGetArrivage,
  useListTransitaires, useCreateTransitaire,
  getListArrivagesQueryKey
} from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import {
  Button, Input, Select, Modal, Label, Table, TableHeader, TableRow,
  TableHead, TableBody, TableCell, Card, Badge
} from "@/components/ui";
import { formatFCFA, formatWeight, formatDate } from "@/lib/utils";
import { Plus, Trash2, Loader2, Search, Eye, Boxes, Package, Settings } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";

const arrivageSchema = z.object({
  transitaireId: z.coerce.number().min(1, "Transitaire requis"),
  dateArrivee: z.string().min(1, "Date requise"),
  nbColisTotal: z.coerce.number().min(1, "Requis"),
  poidsTotal: z.coerce.number().min(0.01, "Requis"),
  coutTotal: z.coerce.number().min(0, "Requis"),
});

const transitaireSchema = z.object({
  nom: z.string().min(1, "Nom requis"),
  code: z.string().min(1, "Code requis"),
  contact: z.string().optional(),
});

type ArrivageForm = z.infer<typeof arrivageSchema>;
type TransitaireForm = z.infer<typeof transitaireSchema>;

function ArrivageDetailModal({ arrivageId, onClose }: { arrivageId: number; onClose: () => void }) {
  const { data: arrivage, isLoading } = useGetArrivage(arrivageId);

  return (
    <Modal isOpen={true} onClose={onClose} title={arrivage ? `Détail — ${arrivage.codeArrivage}` : "Chargement..."} maxWidth="max-w-4xl">
      {isLoading ? (
        <div className="flex justify-center p-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>
      ) : !arrivage ? (
        <p className="text-center text-slate-500 py-8">Arrivage introuvable</p>
      ) : (
        <div className="space-y-6 pt-4">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div className="bg-slate-50 rounded-lg p-3 border border-slate-100">
              <p className="text-xs text-slate-500 uppercase tracking-wide">Transitaire</p>
              <p className="font-bold text-slate-800 mt-1">{arrivage.transitaireCode}</p>
              <p className="text-xs text-slate-500">{arrivage.transitaireNom}</p>
            </div>
            <div className="bg-slate-50 rounded-lg p-3 border border-slate-100">
              <p className="text-xs text-slate-500 uppercase tracking-wide">Date</p>
              <p className="font-bold text-slate-800 mt-1">{formatDate(arrivage.dateArrivee)}</p>
            </div>
            <div className="bg-slate-50 rounded-lg p-3 border border-slate-100">
              <p className="text-xs text-slate-500 uppercase tracking-wide">Poids total</p>
              <p className="font-bold text-slate-800 mt-1">{formatWeight(arrivage.poidsTotal)}</p>
            </div>
            <div className="bg-slate-50 rounded-lg p-3 border border-slate-100">
              <p className="text-xs text-slate-500 uppercase tracking-wide">Coût total</p>
              <p className="font-bold text-slate-800 mt-1">{formatFCFA(arrivage.coutTotal)}</p>
            </div>
          </div>

          <div>
            <h3 className="font-semibold text-slate-800 mb-3 flex items-center gap-2">
              <Boxes className="h-4 w-4" />
              Colis liés ({arrivage.colis?.length ?? 0} / {arrivage.nbColisTotal} prévus)
            </h3>
            {!arrivage.colis || arrivage.colis.length === 0 ? (
              <div className="text-center py-8 text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                <Package className="h-8 w-8 mx-auto mb-2 opacity-40" />
                <p className="text-sm">Aucun colis saisi pour cet arrivage</p>
                <p className="text-xs mt-1">Allez dans la section "Colis" pour en ajouter</p>
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Code colis</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Propriétaire(s)</TableHead>
                    <TableHead className="text-right">Poids</TableHead>
                    <TableHead className="text-right">Montant</TableHead>
                    <TableHead className="text-right">Payé</TableHead>
                    <TableHead className="text-right">Reste</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {arrivage.colis.map((c) => {
                    const totalPaye = c.proprietaires.reduce((s, p) => s + p.montantPaye, 0);
                    const totalSolde = c.proprietaires.reduce((s, p) => s + p.solde, 0);
                    return (
                      <TableRow key={c.id}>
                        <TableCell className="font-bold text-primary">{c.codeColisComplet}</TableCell>
                        <TableCell>
                          <Badge variant={c.type === "individuel" ? "default" : "warning"}>
                            {c.type === "individuel" ? "Individuel" : "Mixte"}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm">
                          {c.proprietaires.map((p) => p.clientNom).join(", ")}
                        </TableCell>
                        <TableCell className="text-right">{formatWeight(c.poids)}</TableCell>
                        <TableCell className="text-right">{formatFCFA(c.montant)}</TableCell>
                        <TableCell className="text-right text-green-600">{formatFCFA(totalPaye)}</TableCell>
                        <TableCell className={`text-right font-semibold ${totalSolde > 0 ? "text-red-600" : "text-green-600"}`}>
                          {formatFCFA(totalSolde)}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </div>
        </div>
      )}
    </Modal>
  );
}

export default function Arrivages() {
  const queryClient = useQueryClient();
  const { data: arrivages, isLoading } = useListArrivages({});
  const { data: transitaires } = useListTransitaires();
  const createMutation = useCreateArrivage();
  const deleteMutation = useDeleteArrivage();
  const createTransitaireMutation = useCreateTransitaire();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isTransitaireModalOpen, setIsTransitaireModalOpen] = useState(false);
  const [detailArrivageId, setDetailArrivageId] = useState<number | null>(null);
  const [searchTerm, setSearchTerm] = useState("");

  const { register, handleSubmit, reset, formState: { errors } } = useForm<ArrivageForm>({
    resolver: zodResolver(arrivageSchema),
    defaultValues: { nbColisTotal: 1, poidsTotal: 0, coutTotal: 0 }
  });

  const {
    register: registerT,
    handleSubmit: handleSubmitT,
    reset: resetT,
    formState: { errors: errorsT }
  } = useForm<TransitaireForm>({ resolver: zodResolver(transitaireSchema) });

  const onSubmit = (data: ArrivageForm) => {
    createMutation.mutate({ data }, {
      onSuccess: () => {
        toast.success("Arrivage créé avec succès");
        queryClient.invalidateQueries({ queryKey: getListArrivagesQueryKey() });
        setIsModalOpen(false);
        reset();
      },
      onError: () => toast.error("Erreur lors de la création"),
    });
  };

  const onSubmitTransitaire = (data: TransitaireForm) => {
    createTransitaireMutation.mutate({ data }, {
      onSuccess: () => {
        toast.success("Transitaire ajouté");
        setIsTransitaireModalOpen(false);
        resetT();
      },
      onError: () => toast.error("Erreur lors de la création du transitaire"),
    });
  };

  const handleDelete = (id: number, code: string) => {
    if (!confirm(`Supprimer l'arrivage ${code} ? Tous les colis associés seront perdus.`)) return;
    deleteMutation.mutate({ id }, {
      onSuccess: () => {
        toast.success("Arrivage supprimé");
        queryClient.invalidateQueries({ queryKey: getListArrivagesQueryKey() });
      },
      onError: () => toast.error("Impossible de supprimer"),
    });
  };

  const filtered = arrivages?.filter((a) =>
    a.codeArrivage.toLowerCase().includes(searchTerm.toLowerCase()) ||
    a.transitaireCode.toLowerCase().includes(searchTerm.toLowerCase()) ||
    a.transitaireNom.toLowerCase().includes(searchTerm.toLowerCase()) ||
    a.dateArrivee.includes(searchTerm)
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-display font-bold text-slate-900">Arrivages</h1>
          <p className="text-slate-500 mt-1">Gérez vos réceptions de marchandises</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => { resetT(); setIsTransitaireModalOpen(true); }}>
            <Settings className="mr-2 h-4 w-4" /> Transitaires
          </Button>
          <Button onClick={() => { reset(); setIsModalOpen(true); }}>
            <Plus className="mr-2 h-4 w-4" /> Nouvel arrivage
          </Button>
        </div>
      </div>

      <Card className="overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex gap-4 bg-white items-center">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input
              placeholder="Rechercher par code, transitaire ou date..."
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
                <TableHead>Code Arrivage</TableHead>
                <TableHead>Transitaire</TableHead>
                <TableHead>Date d'arrivée</TableHead>
                <TableHead className="text-right">Nb Colis</TableHead>
                <TableHead className="text-right">Poids Total</TableHead>
                <TableHead className="text-right">Coût Total</TableHead>
                <TableHead className="w-[100px]"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {!filtered || filtered.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 text-slate-500">
                    Aucun arrivage trouvé
                  </TableCell>
                </TableRow>
              ) : (
                filtered.map((a) => (
                  <TableRow key={a.id} className="cursor-pointer hover:bg-slate-50/80">
                    <TableCell className="font-bold text-primary" onClick={() => setDetailArrivageId(a.id)}>
                      {a.codeArrivage}
                    </TableCell>
                    <TableCell onClick={() => setDetailArrivageId(a.id)}>
                      <div className="flex items-center gap-2">
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-700 border border-blue-200">
                          {a.transitaireCode}
                        </span>
                        <span className="text-slate-500 text-sm">{a.transitaireNom}</span>
                      </div>
                    </TableCell>
                    <TableCell onClick={() => setDetailArrivageId(a.id)}>{formatDate(a.dateArrivee)}</TableCell>
                    <TableCell className="text-right" onClick={() => setDetailArrivageId(a.id)}>{a.nbColisTotal}</TableCell>
                    <TableCell className="text-right" onClick={() => setDetailArrivageId(a.id)}>{formatWeight(a.poidsTotal)}</TableCell>
                    <TableCell className="text-right font-medium" onClick={() => setDetailArrivageId(a.id)}>{formatFCFA(a.coutTotal)}</TableCell>
                    <TableCell>
                      <div className="flex items-center justify-end gap-1">
                        <Button variant="ghost" size="icon" onClick={() => setDetailArrivageId(a.id)} className="text-slate-500 hover:text-primary">
                          <Eye className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => handleDelete(a.id, a.codeArrivage)} className="text-red-500 hover:text-red-700">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        )}
      </Card>

      {/* Formulaire d'arrivage */}
      <Modal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} title="Nouvel arrivage" maxWidth="max-w-lg">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5 pt-4">
          <div className="space-y-2">
            <Label>Transitaire</Label>
            <Select {...register("transitaireId")}>
              <option value={0}>Sélectionnez un transitaire</option>
              {transitaires?.map((t) => (
                <option key={t.id} value={t.id}>{t.code} — {t.nom}</option>
              ))}
            </Select>
            {errors.transitaireId && <span className="text-xs text-red-500">{errors.transitaireId.message}</span>}
            <p className="text-xs text-slate-400">
              Transitaire manquant ?{" "}
              <button type="button" className="text-primary underline" onClick={() => setIsTransitaireModalOpen(true)}>
                Ajouter un transitaire
              </button>
            </p>
          </div>

          <div className="space-y-2">
            <Label>Date d'arrivée</Label>
            <Input type="date" {...register("dateArrivee")} />
            {errors.dateArrivee && <span className="text-xs text-red-500">{errors.dateArrivee.message}</span>}
            <p className="text-xs text-slate-400">Le code sera généré automatiquement : ARR-01-JJMMAA</p>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label>Nb de colis</Label>
              <Input type="number" min={1} {...register("nbColisTotal")} />
              {errors.nbColisTotal && <span className="text-xs text-red-500">{errors.nbColisTotal.message}</span>}
            </div>
            <div className="space-y-2">
              <Label>Poids total (kg)</Label>
              <Input type="number" step="0.1" min={0} {...register("poidsTotal")} />
              {errors.poidsTotal && <span className="text-xs text-red-500">{errors.poidsTotal.message}</span>}
            </div>
            <div className="space-y-2">
              <Label>Coût total (FCFA)</Label>
              <Input type="number" min={0} {...register("coutTotal")} />
              {errors.coutTotal && <span className="text-xs text-red-500">{errors.coutTotal.message}</span>}
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <Button type="button" variant="ghost" onClick={() => setIsModalOpen(false)}>Annuler</Button>
            <Button type="submit" isLoading={createMutation.isPending}>Créer l'arrivage</Button>
          </div>
        </form>
      </Modal>

      {/* Gestion des transitaires */}
      <Modal isOpen={isTransitaireModalOpen} onClose={() => setIsTransitaireModalOpen(false)} title="Gestion des transitaires" maxWidth="max-w-md">
        <div className="space-y-5 pt-4">
          <div>
            <h4 className="text-sm font-semibold text-slate-700 mb-3">Transitaires enregistrés</h4>
            <div className="space-y-2">
              {transitaires?.length === 0 && (
                <p className="text-sm text-slate-400 text-center py-4">Aucun transitaire</p>
              )}
              {transitaires?.map((t) => (
                <div key={t.id} className="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                  <div>
                    <span className="font-bold text-primary text-sm">{t.code}</span>
                    <span className="text-slate-600 text-sm ml-2">— {t.nom}</span>
                    {t.contact && <span className="text-xs text-slate-400 ml-2">{t.contact}</span>}
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="border-t border-slate-100 pt-4">
            <h4 className="text-sm font-semibold text-slate-700 mb-3">Ajouter un transitaire</h4>
            <form onSubmit={handleSubmitT(onSubmitTransitaire)} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs">Code (ex : ZION)</Label>
                  <Input placeholder="ZION" {...registerT("code")} />
                  {errorsT.code && <span className="text-xs text-red-500">{errorsT.code.message}</span>}
                </div>
                <div className="space-y-1">
                  <Label className="text-xs">Nom complet</Label>
                  <Input placeholder="ZION Transit" {...registerT("nom")} />
                  {errorsT.nom && <span className="text-xs text-red-500">{errorsT.nom.message}</span>}
                </div>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Contact (optionnel)</Label>
                <Input placeholder="Téléphone ou email" {...registerT("contact")} />
              </div>
              <Button type="submit" className="w-full" isLoading={createTransitaireMutation.isPending}>
                <Plus className="h-4 w-4 mr-2" /> Ajouter le transitaire
              </Button>
            </form>
          </div>
        </div>
      </Modal>

      {/* Vue détaillée */}
      {detailArrivageId && (
        <ArrivageDetailModal arrivageId={detailArrivageId} onClose={() => setDetailArrivageId(null)} />
      )}
    </div>
  );
}
