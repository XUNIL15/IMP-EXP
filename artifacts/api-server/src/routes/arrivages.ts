import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import {
  arrivagesTable, colisTable, colisProprietairesTable, clientsTable
} from "@workspace/db/schema";
import { eq, gte, lte, and, sql } from "drizzle-orm";

const router: IRouter = Router();

router.get("/arrivages", async (req, res) => {
  try {
    const { dateDebut, dateFin } = req.query;
    const conditions = [];
    if (dateDebut) conditions.push(gte(arrivagesTable.dateArrivee, dateDebut as string));
    if (dateFin) conditions.push(lte(arrivagesTable.dateArrivee, dateFin as string));

    const arrivages = await db
      .select()
      .from(arrivagesTable)
      .where(conditions.length > 0 ? and(...conditions) : undefined)
      .orderBy(arrivagesTable.dateArrivee);

    res.json(arrivages.map(a => ({
      ...a,
      poidsTotal: parseFloat(a.poidsTotal),
      coutTotal: parseFloat(a.coutTotal),
    })));
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.post("/arrivages", async (req, res) => {
  try {
    const { dateArrivee, nbColisTotal, poidsTotal, coutTotal } = req.body;

    const [arrivage] = await db
      .insert(arrivagesTable)
      .values({ dateArrivee, nbColisTotal, poidsTotal: String(poidsTotal), coutTotal: String(coutTotal) })
      .returning();

    res.status(201).json({
      ...arrivage,
      poidsTotal: parseFloat(arrivage.poidsTotal),
      coutTotal: parseFloat(arrivage.coutTotal),
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/arrivages/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const [arrivage] = await db.select().from(arrivagesTable).where(eq(arrivagesTable.id, id));
    if (!arrivage) return res.status(404).json({ error: "Arrivage non trouvé" });

    const colisList = await db
      .select({
        id: colisTable.id,
        arrivageId: colisTable.arrivageId,
        codeColisReel: colisTable.codeColisReel,
        codeColisComplet: colisTable.codeColisComplet,
        type: colisTable.type,
        poids: colisTable.poids,
        montant: colisTable.montant,
        dateCreation: colisTable.dateCreation,
      })
      .from(colisTable)
      .where(eq(colisTable.arrivageId, id));

    const colisWithProprietaires = await Promise.all(
      colisList.map(async (c) => {
        const proprietaires = await db
          .select({
            id: colisProprietairesTable.id,
            colisId: colisProprietairesTable.colisId,
            clientId: colisProprietairesTable.clientId,
            clientNom: clientsTable.nom,
            clientTelephone: clientsTable.telephone,
            poids: colisProprietairesTable.poids,
            montantDu: colisProprietairesTable.montantDu,
            montantPaye: colisProprietairesTable.montantPaye,
            solde: colisProprietairesTable.solde,
          })
          .from(colisProprietairesTable)
          .innerJoin(clientsTable, eq(colisProprietairesTable.clientId, clientsTable.id))
          .where(eq(colisProprietairesTable.colisId, c.id));

        return {
          ...c,
          poids: parseFloat(c.poids),
          montant: parseFloat(c.montant),
          arrivageDate: arrivage.dateArrivee,
          proprietaires: proprietaires.map(p => ({
            ...p,
            poids: parseFloat(p.poids),
            montantDu: parseFloat(p.montantDu),
            montantPaye: parseFloat(p.montantPaye),
            solde: parseFloat(p.solde),
          })),
        };
      })
    );

    res.json({
      ...arrivage,
      poidsTotal: parseFloat(arrivage.poidsTotal),
      coutTotal: parseFloat(arrivage.coutTotal),
      colis: colisWithProprietaires,
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.put("/arrivages/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const { dateArrivee, nbColisTotal, poidsTotal, coutTotal } = req.body;

    const [arrivage] = await db
      .update(arrivagesTable)
      .set({ dateArrivee, nbColisTotal, poidsTotal: String(poidsTotal), coutTotal: String(coutTotal) })
      .where(eq(arrivagesTable.id, id))
      .returning();

    if (!arrivage) return res.status(404).json({ error: "Arrivage non trouvé" });

    res.json({
      ...arrivage,
      poidsTotal: parseFloat(arrivage.poidsTotal),
      coutTotal: parseFloat(arrivage.coutTotal),
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.delete("/arrivages/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await db.delete(arrivagesTable).where(eq(arrivagesTable.id, id));
    res.status(204).send();
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

export default router;
