import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import {
  colisTable, colisProprietairesTable, clientsTable, arrivagesTable
} from "@workspace/db/schema";
import { eq, and, gte, lte, sql } from "drizzle-orm";

const router: IRouter = Router();

function formatDate(dateStr: string): string {
  const d = new Date(dateStr);
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const yy = String(d.getFullYear()).slice(-2);
  return `${dd}${mm}${yy}`;
}

async function getColisWithProprietaires(colisId: number, arrivageDate: string) {
  const [colis] = await db.select().from(colisTable).where(eq(colisTable.id, colisId));
  if (!colis) return null;

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
    .where(eq(colisProprietairesTable.colisId, colisId));

  return {
    ...colis,
    poids: parseFloat(colis.poids),
    montant: parseFloat(colis.montant),
    arrivageDate,
    proprietaires: proprietaires.map(p => ({
      ...p,
      poids: parseFloat(p.poids),
      montantDu: parseFloat(p.montantDu),
      montantPaye: parseFloat(p.montantPaye),
      solde: parseFloat(p.solde),
    })),
  };
}

router.get("/colis", async (req, res) => {
  try {
    const { arrivageId, clientId, type, dateDebut, dateFin } = req.query;

    let query = db
      .select({
        id: colisTable.id,
        arrivageId: colisTable.arrivageId,
        codeColisReel: colisTable.codeColisReel,
        codeColisComplet: colisTable.codeColisComplet,
        type: colisTable.type,
        poids: colisTable.poids,
        montant: colisTable.montant,
        dateCreation: colisTable.dateCreation,
        arrivageDate: arrivagesTable.dateArrivee,
      })
      .from(colisTable)
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id));

    const conditions: any[] = [];
    if (arrivageId) conditions.push(eq(colisTable.arrivageId, parseInt(arrivageId as string)));
    if (type) conditions.push(eq(colisTable.type, type as "individuel" | "mixte"));
    if (dateDebut) conditions.push(gte(arrivagesTable.dateArrivee, dateDebut as string));
    if (dateFin) conditions.push(lte(arrivagesTable.dateArrivee, dateFin as string));

    const colisList = await query.where(conditions.length > 0 ? and(...conditions) : undefined).orderBy(arrivagesTable.dateArrivee);

    let filtered = colisList;
    if (clientId) {
      const cp = await db
        .select({ colisId: colisProprietairesTable.colisId })
        .from(colisProprietairesTable)
        .where(eq(colisProprietairesTable.clientId, parseInt(clientId as string)));
      const ids = new Set(cp.map(x => x.colisId));
      filtered = colisList.filter(c => ids.has(c.id));
    }

    const result = await Promise.all(
      filtered.map(async (c) => {
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

    res.json(result);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.post("/colis", async (req, res) => {
  try {
    const { arrivageId, codeColisReel, type, poids, montant, proprietaires } = req.body;

    const [arrivage] = await db.select().from(arrivagesTable).where(eq(arrivagesTable.id, arrivageId));
    if (!arrivage) return res.status(404).json({ error: "Arrivage non trouvé" });

    const dateSuffix = formatDate(arrivage.dateArrivee);
    const codeColisComplet = `${codeColisReel}_${dateSuffix}`;

    const [colis] = await db
      .insert(colisTable)
      .values({
        arrivageId,
        codeColisReel,
        codeColisComplet,
        type,
        poids: String(poids),
        montant: String(montant),
      })
      .returning();

    if (proprietaires && proprietaires.length > 0) {
      await db.insert(colisProprietairesTable).values(
        proprietaires.map((p: any) => ({
          colisId: colis.id,
          clientId: p.clientId,
          poids: String(p.poids),
          montantDu: String(p.montantDu),
          montantPaye: String(p.montantPaye || 0),
          solde: String(p.montantDu - (p.montantPaye || 0)),
        }))
      );
    }

    const result = await getColisWithProprietaires(colis.id, arrivage.dateArrivee);
    res.status(201).json(result);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/colis/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const [colisWithArrivage] = await db
      .select({ arrivageDate: arrivagesTable.dateArrivee })
      .from(colisTable)
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id))
      .where(eq(colisTable.id, id));

    if (!colisWithArrivage) return res.status(404).json({ error: "Colis non trouvé" });

    const result = await getColisWithProprietaires(id, colisWithArrivage.arrivageDate);
    res.json(result);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.put("/colis/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const { arrivageId, codeColisReel, type, poids, montant, proprietaires } = req.body;

    const [arrivage] = await db.select().from(arrivagesTable).where(eq(arrivagesTable.id, arrivageId));
    if (!arrivage) return res.status(404).json({ error: "Arrivage non trouvé" });

    const dateSuffix = formatDate(arrivage.dateArrivee);
    const codeColisComplet = `${codeColisReel}_${dateSuffix}`;

    const [colis] = await db
      .update(colisTable)
      .set({ arrivageId, codeColisReel, codeColisComplet, type, poids: String(poids), montant: String(montant) })
      .where(eq(colisTable.id, id))
      .returning();

    if (!colis) return res.status(404).json({ error: "Colis non trouvé" });

    await db.delete(colisProprietairesTable).where(eq(colisProprietairesTable.colisId, id));

    if (proprietaires && proprietaires.length > 0) {
      await db.insert(colisProprietairesTable).values(
        proprietaires.map((p: any) => ({
          colisId: id,
          clientId: p.clientId,
          poids: String(p.poids),
          montantDu: String(p.montantDu),
          montantPaye: String(p.montantPaye || 0),
          solde: String(p.montantDu - (p.montantPaye || 0)),
        }))
      );
    }

    const result = await getColisWithProprietaires(id, arrivage.dateArrivee);
    res.json(result);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.delete("/colis/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await db.delete(colisTable).where(eq(colisTable.id, id));
    res.status(204).send();
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

export default router;
