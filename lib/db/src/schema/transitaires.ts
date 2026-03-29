import { pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const transitairesTable = pgTable("transitaires", {
  id: serial("id").primaryKey(),
  nom: text("nom").notNull(),
  code: text("code").notNull().unique(),
  contact: text("contact"),
  dateCreation: timestamp("date_creation").defaultNow().notNull(),
});

export const insertTransitaireSchema = createInsertSchema(transitairesTable).omit({ id: true, dateCreation: true });
export type InsertTransitaire = z.infer<typeof insertTransitaireSchema>;
export type Transitaire = typeof transitairesTable.$inferSelect;
