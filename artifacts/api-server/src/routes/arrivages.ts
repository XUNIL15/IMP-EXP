import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import {
  arrivagesTable, colisTable, colisProprietairesTable, clientsTable, transitairesTable
} from "@workspace/db/schema";
import { eq, gte, lte, and, sql } from "drizzle-orm";

const router: IRouter = Router();

function formatDateCode(dateStr: string): string {
  const d = new Date(dateStr);
  const dd = String(d.getUTCDate()).padStart(2, "0");
  const mm = String(d.getUTCMonth() + 1).padStart(2, "0");
  const yy = String(d.getUTCFullYear()).slice(-2);
  return `${dd}${mm}${yy}`;
}

async function generateCodeArrivage(dateArrivee: string, excludeId?: number): Promise<string> {
  const dateSuffix = formatDateCode(dateArrivee);
  const existingOnDate = await db
    .select({ id: arrivagesTable.id })
    .from(arrivagesTable)
    .where(eq(arrivagesTable.dateArrivee, dateArrivee));

  const count = excludeId
    ? existingOnDate.filter(a => a.id !== excludeId).length
    : existingOnDate.length;

  const seq = String(count + 1).padStart(2, "0");
  return `ARR-${seq}-${dateSuffix}`;
}

async function arrivageWithTransitaire(arrivage: typeof arrivagesTable.$inferSelect) {
  const [transitaire] = await db
    .select()
    .from(transitairesTable)
    .where(eq(transitairesTable.id, arrivage.transitaireId));

  return {
    ...arrivage,
    poidsTotal: parseFloat(arrivage.poidsTotal),
    coutTotal: parseFloat(arrivage.coutTotal),
    transitaireNom: transitaire?.nom ?? "",
    transitaireCode: transitaire?.code ?? "",
  };
}

router.get("/arrivages", async (req, res) => {
  try {
    const { dateDebut, dateFin } = req.query;
    const conditions = [];
    if (dateDebut) conditions.push(gte(arrivagesTable.dateArrivee, dateDebut as string));
    if (dateFin) conditions.push(lte(arrivagesTable.dateArrivee, dateFin as string));

    const arrivages = await db
      .select({
        id: arrivagesTable.id,
        codeArrivage: arrivagesTable.codeArrivage,
        transitaireId: arrivagesTable.transitaireId,
        transitaireNom: transitairesTable.nom,
        transitaireCode: transitairesTable.code,
        dateArrivee: arrivagesTable.dateArrivee,
        nbColisTotal: arrivagesTable.nbColisTotal,
        poidsTotal: arrivagesTable.poidsTotal,
        coutTotal: arrivagesTable.coutTotal,
        dateCreation: arrivagesTable.dateCreation,
      })
      .from(arrivagesTable)
      .innerJoin(transitairesTable, eq(arrivagesTable.transitaireId, transitairesTable.id))
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
    const { transitaireId, dateArrivee, nbColisTotal, poidsTotal, coutTotal } = req.body;

    const codeArrivage = await generateCodeArrivage(dateArrivee);

    const [arrivage] = await db
      .insert(arrivagesTable)
      .values({
        codeArrivage,
        transitaireId,
        dateArrivee,
        nbColisTotal,
        poidsTotal: String(poidsTotal),
        coutTotal: String(coutTotal),
      })
      .returning();

    res.status(201).json(await arrivageWithTransitaire(arrivage));
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/arrivages/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);

    const [arrivageRow] = await db
      .select({
        id: arrivagesTable.id,
        codeArrivage: arrivagesTable.codeArrivage,
        transitaireId: arrivagesTable.transitaireId,
        transitaireNom: transitairesTable.nom,
        transitaireCode: transitairesTable.code,
        dateArrivee: arrivagesTable.dateArrivee,
        nbColisTotal: arrivagesTable.nbColisTotal,
        poidsTotal: arrivagesTable.poidsTotal,
        coutTotal: arrivagesTable.coutTotal,
        dateCreation: arrivagesTable.dateCreation,
      })
      .from(arrivagesTable)
      .innerJoin(transitairesTable, eq(arrivagesTable.transitaireId, transitairesTable.id))
      .where(eq(arrivagesTable.id, id));

    if (!arrivageRow) return res.status(404).json({ error: "Arrivage non trouvé" });

    const colisList = await db
      .select()
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
          arrivageDate: arrivageRow.dateArrivee,
          arrivageCode: arrivageRow.codeArrivage,
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
      ...arrivageRow,
      poidsTotal: parseFloat(arrivageRow.poidsTotal),
      coutTotal: parseFloat(arrivageRow.coutTotal),
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
    const { transitaireId, dateArrivee, nbColisTotal, poidsTotal, coutTotal } = req.body;

    // Regenerate code if date changed
    const [existing] = await db.select().from(arrivagesTable).where(eq(arrivagesTable.id, id));
    let codeArrivage = existing?.codeArrivage;
    if (existing && existing.dateArrivee !== dateArrivee) {
      codeArrivage = await generateCodeArrivage(dateArrivee, id);
    }

    const [arrivage] = await db
      .update(arrivagesTable)
      .set({ transitaireId, dateArrivee, nbColisTotal, poidsTotal: String(poidsTotal), coutTotal: String(coutTotal), codeArrivage })
      .where(eq(arrivagesTable.id, id))
      .returning();

    if (!arrivage) return res.status(404).json({ error: "Arrivage non trouvé" });

    res.json(await arrivageWithTransitaire(arrivage));
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
