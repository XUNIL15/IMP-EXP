import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import { clientsTable, paiementsTable, colisProprietairesTable, colisTable, arrivagesTable } from "@workspace/db/schema";
import { eq, ilike, sql } from "drizzle-orm";

const router: IRouter = Router();

router.get("/clients", async (req, res) => {
  try {
    const search = req.query.search as string | undefined;

    const clients = await db.select().from(clientsTable).orderBy(clientsTable.nom);

    const result = await Promise.all(
      clients.map(async (client) => {
        if (search && !client.nom.toLowerCase().includes(search.toLowerCase()) &&
            !client.telephone.includes(search)) {
          return null;
        }

        const totals = await db
          .select({
            totalDu: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantDu}), 0)`,
            totalPaye: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantPaye}), 0)`,
            solde: sql<string>`COALESCE(SUM(${colisProprietairesTable.solde}), 0)`,
          })
          .from(colisProprietairesTable)
          .where(eq(colisProprietairesTable.clientId, client.id));

        return {
          ...client,
          totalDu: parseFloat(totals[0]?.totalDu || "0"),
          totalPaye: parseFloat(totals[0]?.totalPaye || "0"),
          solde: parseFloat(totals[0]?.solde || "0"),
        };
      })
    );

    res.json(result.filter(Boolean));
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.post("/clients", async (req, res) => {
  try {
    const { nom, telephone, adresse } = req.body;
    if (!nom || !telephone) {
      return res.status(400).json({ error: "Nom et téléphone requis" });
    }

    const [client] = await db.insert(clientsTable).values({ nom, telephone, adresse }).returning();
    res.status(201).json({ ...client, totalDu: 0, totalPaye: 0, solde: 0 });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/clients/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const [client] = await db.select().from(clientsTable).where(eq(clientsTable.id, id));
    if (!client) return res.status(404).json({ error: "Client non trouvé" });

    const totals = await db
      .select({
        totalDu: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantDu}), 0)`,
        totalPaye: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantPaye}), 0)`,
        solde: sql<string>`COALESCE(SUM(${colisProprietairesTable.solde}), 0)`,
      })
      .from(colisProprietairesTable)
      .where(eq(colisProprietairesTable.clientId, id));

    res.json({
      ...client,
      totalDu: parseFloat(totals[0]?.totalDu || "0"),
      totalPaye: parseFloat(totals[0]?.totalPaye || "0"),
      solde: parseFloat(totals[0]?.solde || "0"),
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.put("/clients/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const { nom, telephone, adresse } = req.body;

    const [client] = await db
      .update(clientsTable)
      .set({ nom, telephone, adresse })
      .where(eq(clientsTable.id, id))
      .returning();

    if (!client) return res.status(404).json({ error: "Client non trouvé" });

    const totals = await db
      .select({
        totalDu: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantDu}), 0)`,
        totalPaye: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantPaye}), 0)`,
        solde: sql<string>`COALESCE(SUM(${colisProprietairesTable.solde}), 0)`,
      })
      .from(colisProprietairesTable)
      .where(eq(colisProprietairesTable.clientId, id));

    res.json({
      ...client,
      totalDu: parseFloat(totals[0]?.totalDu || "0"),
      totalPaye: parseFloat(totals[0]?.totalPaye || "0"),
      solde: parseFloat(totals[0]?.solde || "0"),
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.delete("/clients/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await db.delete(clientsTable).where(eq(clientsTable.id, id));
    res.status(204).send();
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/clients/:id/historique-paiements", async (req, res) => {
  try {
    const id = parseInt(req.params.id);

    const paiements = await db
      .select({
        id: paiementsTable.id,
        colisCode: colisTable.codeColisComplet,
        montant: paiementsTable.montant,
        datePaiement: paiementsTable.datePaiement,
        mode: paiementsTable.mode,
        montantDu: colisProprietairesTable.montantDu,
        soldeApres: colisProprietairesTable.solde,
      })
      .from(paiementsTable)
      .innerJoin(colisProprietairesTable, eq(paiementsTable.colisProprietaireId, colisProprietairesTable.id))
      .innerJoin(colisTable, eq(colisProprietairesTable.colisId, colisTable.id))
      .where(eq(paiementsTable.clientId, id))
      .orderBy(paiementsTable.datePaiement);

    res.json(paiements.map(p => ({
      ...p,
      montant: parseFloat(p.montant),
      montantDu: parseFloat(p.montantDu),
      soldeApres: parseFloat(p.soldeApres),
    })));
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

export default router;
