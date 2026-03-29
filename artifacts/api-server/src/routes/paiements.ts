import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import {
  paiementsTable, colisProprietairesTable, clientsTable, colisTable, arrivagesTable
} from "@workspace/db/schema";
import { eq, and, gte, lte, sql, gt } from "drizzle-orm";

const router: IRouter = Router();

router.post("/colis-proprietaires/:id/paiements", async (req, res) => {
  try {
    const cpId = parseInt(req.params.id);
    const { montant, mode } = req.body;

    const [cp] = await db
      .select()
      .from(colisProprietairesTable)
      .where(eq(colisProprietairesTable.id, cpId));

    if (!cp) return res.status(404).json({ error: "Propriétaire de colis non trouvé" });

    const currentPaye = parseFloat(cp.montantPaye);
    const currentDu = parseFloat(cp.montantDu);
    const newPaye = Math.min(currentPaye + montant, currentDu);
    const newSolde = currentDu - newPaye;

    await db
      .update(colisProprietairesTable)
      .set({
        montantPaye: String(newPaye),
        solde: String(newSolde),
      })
      .where(eq(colisProprietairesTable.id, cpId));

    const [paiement] = await db
      .insert(paiementsTable)
      .values({
        clientId: cp.clientId,
        colisProprietaireId: cpId,
        montant: String(montant),
        mode,
      })
      .returning();

    res.status(201).json({
      ...paiement,
      montant: parseFloat(paiement.montant),
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/dettes", async (req, res) => {
  try {
    const { clientId, dateDebut, dateFin } = req.query;

    const conditions: any[] = [gt(colisProprietairesTable.solde, "0")];
    if (clientId) conditions.push(eq(colisProprietairesTable.clientId, parseInt(clientId as string)));
    if (dateDebut) conditions.push(gte(arrivagesTable.dateArrivee, dateDebut as string));
    if (dateFin) conditions.push(lte(arrivagesTable.dateArrivee, dateFin as string));

    const dettes = await db
      .select({
        colisProprietaireId: colisProprietairesTable.id,
        clientId: colisProprietairesTable.clientId,
        clientNom: clientsTable.nom,
        clientTelephone: clientsTable.telephone,
        colisCode: colisTable.codeColisComplet,
        arrivageDate: arrivagesTable.dateArrivee,
        montantDu: colisProprietairesTable.montantDu,
        montantPaye: colisProprietairesTable.montantPaye,
        solde: colisProprietairesTable.solde,
      })
      .from(colisProprietairesTable)
      .innerJoin(clientsTable, eq(colisProprietairesTable.clientId, clientsTable.id))
      .innerJoin(colisTable, eq(colisProprietairesTable.colisId, colisTable.id))
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id))
      .where(and(...conditions))
      .orderBy(clientsTable.nom);

    res.json(dettes.map(d => ({
      ...d,
      montantDu: parseFloat(d.montantDu),
      montantPaye: parseFloat(d.montantPaye),
      solde: parseFloat(d.solde),
    })));
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

export default router;
