import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import { transitairesTable } from "@workspace/db/schema";
import { eq } from "drizzle-orm";

const router: IRouter = Router();

router.get("/transitaires", async (req, res) => {
  try {
    const transitaires = await db.select().from(transitairesTable).orderBy(transitairesTable.nom);
    res.json(transitaires);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.post("/transitaires", async (req, res) => {
  try {
    const { nom, code, contact } = req.body;
    if (!nom || !code) return res.status(400).json({ error: "Nom et code requis" });

    const [transitaire] = await db.insert(transitairesTable).values({ nom, code, contact }).returning();
    res.status(201).json(transitaire);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.put("/transitaires/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    const { nom, code, contact } = req.body;

    const [transitaire] = await db
      .update(transitairesTable)
      .set({ nom, code, contact })
      .where(eq(transitairesTable.id, id))
      .returning();

    if (!transitaire) return res.status(404).json({ error: "Transitaire non trouvé" });
    res.json(transitaire);
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

router.delete("/transitaires/:id", async (req, res) => {
  try {
    const id = parseInt(req.params.id);
    await db.delete(transitairesTable).where(eq(transitairesTable.id, id));
    res.status(204).send();
  } catch (err) {
    req.log.error(err);
    res.status(500).json({ error: "Erreur serveur" });
  }
});

export default router;
