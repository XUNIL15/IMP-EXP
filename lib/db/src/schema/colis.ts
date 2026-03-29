import { pgTable, serial, integer, text, numeric, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";
import { arrivagesTable } from "./arrivages";

export const colisTable = pgTable("colis", {
  id: serial("id").primaryKey(),
  arrivageId: integer("arrivage_id").notNull().references(() => arrivagesTable.id, { onDelete: "cascade" }),
  codeColisReel: text("code_colis_reel").notNull(),
  codeColisComplet: text("code_colis_complet").notNull().unique(),
  type: text("type").notNull().$type<"individuel" | "mixte">(),
  poids: numeric("poids", { precision: 10, scale: 2 }).notNull(),
  montant: numeric("montant", { precision: 12, scale: 2 }).notNull(),
  dateCreation: timestamp("date_creation").defaultNow().notNull(),
});

export const insertColisSchema = createInsertSchema(colisTable).omit({ id: true, dateCreation: true });
export type InsertColis = z.infer<typeof insertColisSchema>;
export type Colis = typeof colisTable.$inferSelect;
