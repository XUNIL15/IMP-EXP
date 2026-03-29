import { Router, type IRouter } from "express";
import healthRouter from "./health";
import transitairesRouter from "./transitaires";
import clientsRouter from "./clients";
import arrivagesRouter from "./arrivages";
import colisRouter from "./colis";
import paiementsRouter from "./paiements";
import rapportsRouter from "./rapports";

const router: IRouter = Router();

router.use(healthRouter);
router.use(transitairesRouter);
router.use(clientsRouter);
router.use(arrivagesRouter);
router.use(colisRouter);
router.use(paiementsRouter);
router.use(rapportsRouter);

export default router;
