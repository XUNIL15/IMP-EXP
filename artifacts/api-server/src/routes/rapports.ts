import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import {
  arrivagesTable, colisTable, colisProprietairesTable, clientsTable
} from "@workspace/db/schema";
import { eq, and, gte, lte, sql, gt } from "drizzle-orm";

const router: IRouter = Router();

router.get("/dashboard", async (req, res) => {
  try {
    const today = (req.query.date as string) || new Date().toISOString().split("T")[0];

    const todayStats = await db
      .select({
        nbColis: sql<number>`COUNT(DISTINCT ${colisTable.id})`,
        poidsTotal: sql<string>`COALESCE(SUM(${colisTable.poids}), 0)`,
        montantTotal: sql<string>`COALESCE(SUM(${colisTable.montant}), 0)`,
        montantEncaisse: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantPaye}), 0)`,
      })
      .from(colisTable)
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id))
      .leftJoin(colisProprietairesTable, eq(colisProprietairesTable.colisId, colisTable.id))
      .where(eq(arrivagesTable.dateArrivee, today));

    const dettesToday = await db
      .select({
        dettesTotal: sql<string>`COALESCE(SUM(${colisProprietairesTable.solde}), 0)`,
      })
      .from(colisProprietairesTable)
      .innerJoin(colisTable, eq(colisProprietairesTable.colisId, colisTable.id))
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id))
      .where(and(
        eq(arrivagesTable.dateArrivee, today),
        gt(colisProprietairesTable.solde, "0")
      ));

    const totalDettesGlobalResult = await db
      .select({
        total: sql<string>`COALESCE(SUM(${colisProprietairesTable.solde}), 0)`,
      })
      .from(colisProprietairesTable)
      .where(gt(colisProprietairesTable.solde, "0"));

    const last30Days = new Date();
    last30Days.setDate(last30Days.getDate() - 30);
    const last30DaysStr = last30Days.toISOString().split("T")[0];

    const evolutionColis = await db
      .select({
        date: arrivagesTable.dateArrivee,
        nbColis: sql<number>`COUNT(${colisTable.id})`,
        montant: sql<string>`COALESCE(SUM(${colisTable.montant}), 0)`,
      })
      .from(colisTable)
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id))
      .where(gte(arrivagesTable.dateArrivee, last30DaysStr))
      .groupBy(arrivagesTable.dateArrivee)
      .orderBy(arrivagesTable.dateArrivee);

    const topClients = await db
      .select({
        clientId: clientsTable.id,
        clientNom: clientsTable.nom,
        totalDu: sql<string>`COALESCE(SUM(${colisProprietairesTable.montantDu}), 0)`,
        solde: sql<string>`COALESCE(SUM(${colisProprietairesTable.solde}), 0)`,
      })
      .from(colisProprietairesTable)
      .innerJoin(clientsTable, eq(colisProprietairesTable.clientId, clientsTable.id))
      .where(gt(colisProprietairesTable.solde, "0"))
      .groupBy(clientsTable.id, clientsTable.nom)
      .orderBy(sql`SUM(${colisProprietairesTable.solde}) DESC`)
      .limit(5);

    res.json({
      today: {
        nbColis: Number(todayStats[0]?.nbColis || 0),
        poidsTotal: parseFloat(todayStats[0]?.poidsTotal || "0"),
        montantTotal: parseFloat(todayStats[0]?.montantTotal || "0"),
        montantEncaisse: parseFloat(todayStats[0]?.montantEncaisse || "0"),
        dettesTotal: parseFloat(dettesToday[0]?.dettesTotal || "0"),
      },
      evolutionColis: evolutionColis.map(e => ({
        date: e.date,
        nbColis: Number(e.nbColis),
        montant: parseFloat(e.montant),
      })),
      topClients: topClients.map(c => ({
        clientId: c.clientId,
        clientNom: c.clientNom,
        totalDu: parseFloat(c.totalDu),
        solde: parseFloat(c.solde),
      })),
      totalDettesGlobal: parseFloat(totalDettesGlobalResult[0]?.total || "0"),
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.get("/bilan-journalier", async (req, res) => {
  try {
    const date = req.query.date as string;
    if (!date) return res.status(400).json({ error: "Date requise" });

    const colisOfDay = await db
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
      .innerJoin(arrivagesTable, eq(colisTable.arrivageId, arrivagesTable.id))
      .where(eq(arrivagesTable.dateArrivee, date));

    const colisWithProprietaires = await Promise.all(
      colisOfDay.map(async (c) => {
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

    const individuels = colisWithProprietaires.filter(c => c.type === "individuel");
    const mixtes = colisWithProprietaires.filter(c => c.type === "mixte");

    const dettesOfDay = await db
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
      .where(and(
        eq(arrivagesTable.dateArrivee, date),
        gt(colisProprietairesTable.solde, "0")
      ));

    const montantTotal = colisWithProprietaires.reduce((sum, c) => sum + c.montant, 0);
    const montantEncaisse = colisWithProprietaires.reduce((sum, c) =>
      sum + c.proprietaires.reduce((s, p) => s + p.montantPaye, 0), 0);
    const montantDu = colisWithProprietaires.reduce((sum, c) =>
      sum + c.proprietaires.reduce((s, p) => s + p.montantDu, 0), 0);

    res.json({
      date,
      nbColisIndividuels: individuels.length,
      nbColisMixtes: mixtes.length,
      poidsIndividuels: individuels.reduce((sum, c) => sum + c.poids, 0),
      poidsMixtes: mixtes.reduce((sum, c) => sum + c.poids, 0),
      montantTotal,
      montantEncaisse,
      montantDu,
      dettes: dettesOfDay.map(d => ({
        ...d,
        montantDu: parseFloat(d.montantDu),
        montantPaye: parseFloat(d.montantPaye),
        solde: parseFloat(d.solde),
      })),
      colis: colisWithProprietaires,
    });
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

export default router;
