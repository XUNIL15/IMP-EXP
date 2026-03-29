import { useState } from "react";
import { useGetBilanJournalier } from "@workspace/api-client-react";
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from "@/components/ui";
import { formatFCFA, formatWeight, formatDate } from "@/lib/utils";
import { Download, FileText, Loader2, Calendar } from "lucide-react";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import Papa from "papaparse";

export default function Bilan() {
  const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
  
  const { data: bilan, isLoading } = useGetBilanJournalier(
    { date: selectedDate },
    { query: { enabled: !!selectedDate } }
  );

  const exportPDF = () => {
    if (!bilan) return;
    
    const doc = new jsPDF();
    doc.setFont("helvetica", "bold");
    doc.setFontSize(20);
    doc.text("TRANSIT PRO", 14, 20);
    
    doc.setFontSize(14);
    doc.text(`Bilan Journalier - ${formatDate(bilan.date)}`, 14, 30);
    
    doc.setFontSize(11);
    doc.setFont("helvetica", "normal");
    doc.text(`Total Colis : ${bilan.nbColisIndividuels + bilan.nbColisMixtes}`, 14, 45);
    doc.text(`Poids Total : ${formatWeight(bilan.poidsIndividuels + bilan.poidsMixtes)}`, 14, 52);
    doc.text(`Montant Encaissé : ${formatFCFA(bilan.montantEncaisse)}`, 120, 45);
    doc.text(`Nouveaux Impayés : ${formatFCFA(bilan.montantDu)}`, 120, 52);

    doc.setFont("helvetica", "bold");
    doc.text("Détail des colis du jour", 14, 65);

    const tableData = bilan.colis.map(c => [
      c.codeColisComplet,
      c.type,
      formatWeight(c.poids),
      formatFCFA(c.montant)
    ]);

    autoTable(doc, {
      startY: 70,
      head: [['Code Colis', 'Type', 'Poids', 'Montant']],
      body: tableData,
      theme: 'grid',
      headStyles: { fillColor: [37, 99, 235] }
    });

    if (bilan.dettes.length > 0) {
      const finalY = (doc as any).lastAutoTable.finalY || 70;
      doc.text("Détail des impayés (Dettes créées ce jour)", 14, finalY + 15);
      
      const dettesData = bilan.dettes.map(d => [
        d.clientNom,
        d.colisCode,
        formatFCFA(d.montantDu),
        formatFCFA(d.montantPaye),
        formatFCFA(Math.abs(d.solde))
      ]);

      autoTable(doc, {
        startY: finalY + 20,
        head: [['Client', 'Colis', 'Total', 'Avance', 'Reste à payer']],
        body: dettesData,
        theme: 'striped',
        headStyles: { fillColor: [220, 38, 38] }
      });
    }

    doc.save(`Bilan_${selectedDate}.pdf`);
  };

  const exportCSV = () => {
    if (!bilan) return;
    
    const csvData = bilan.colis.map(c => ({
      Date: formatDate(bilan.date),
      Code_Colis: c.codeColisComplet,
      Type: c.type,
      Poids_kg: c.poids,
      Montant_FCFA: c.montant
    }));

    const csv = Papa.unparse(csvData);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `Colis_${selectedDate}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-display font-bold text-slate-900">Bilan Journalier</h1>
          <p className="text-slate-500 mt-1">Consultez et exportez les rapports de fin de journée</p>
        </div>
        
        <div className="flex flex-col sm:flex-row items-center gap-3">
          <div className="relative">
            <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
            <Input 
              type="date" 
              value={selectedDate} 
              onChange={(e) => setSelectedDate(e.target.value)}
              className="pl-10 w-[200px] bg-white"
            />
          </div>
          <Button variant="outline" onClick={exportCSV} disabled={!bilan || isLoading}>
            <FileText className="mr-2 h-4 w-4" /> CSV
          </Button>
          <Button onClick={exportPDF} disabled={!bilan || isLoading}>
            <Download className="mr-2 h-4 w-4" /> PDF
          </Button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center p-20"><Loader2 className="h-10 w-10 animate-spin text-primary" /></div>
      ) : !bilan ? (
        <Card className="p-12 text-center text-slate-500">Aucune donnée pour cette date.</Card>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card className="bg-primary text-primary-foreground border-transparent">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium opacity-80">Total Encaissé</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-bold">{formatFCFA(bilan.montantEncaisse)}</div>
              </CardContent>
            </Card>

            <Card className="bg-red-50 border-red-100">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-red-600">Total Impayés du jour</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-bold text-red-700">{formatFCFA(bilan.montantDu)}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-slate-500">Volume (Individuel)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-slate-900">{bilan.nbColisIndividuels} colis</div>
                <p className="text-xs text-slate-500 mt-1">{formatWeight(bilan.poidsIndividuels)}</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-slate-500">Volume (Mixte)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-slate-900">{bilan.nbColisMixtes} colis</div>
                <p className="text-xs text-slate-500 mt-1">{formatWeight(bilan.poidsMixtes)}</p>
              </CardContent>
            </Card>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <Card>
              <CardHeader>
                <CardTitle>Colis enregistrés ({bilan.colis.length})</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Code</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead className="text-right">Montant</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {bilan.colis.slice(0, 5).map(c => (
                      <TableRow key={c.id}>
                        <TableCell className="font-medium">{c.codeColisComplet}</TableCell>
                        <TableCell className="capitalize">{c.type}</TableCell>
                        <TableCell className="text-right font-bold">{formatFCFA(c.montant)}</TableCell>
                      </TableRow>
                    ))}
                    {bilan.colis.length > 5 && (
                      <TableRow>
                        <TableCell colSpan={3} className="text-center text-sm text-slate-500 py-3 bg-slate-50">
                          + {bilan.colis.length - 5} autres colis (Voir l'export PDF complet)
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            <Card className="border-red-100">
              <CardHeader>
                <CardTitle className="text-red-600">Dettes créées ce jour ({bilan.dettes.length})</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Client</TableHead>
                      <TableHead>Colis</TableHead>
                      <TableHead className="text-right">Reste à payer</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {bilan.dettes.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={3} className="text-center text-slate-500 py-8">Aucun impayé aujourd'hui</TableCell>
                      </TableRow>
                    ) : (
                      bilan.dettes.map(d => (
                        <TableRow key={d.colisProprietaireId}>
                          <TableCell className="font-medium">{d.clientNom}</TableCell>
                          <TableCell>{d.colisCode}</TableCell>
                          <TableCell className="text-right font-bold text-red-600">{formatFCFA(Math.abs(d.solde))}</TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}
