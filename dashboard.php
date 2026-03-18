<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <title>Dashboard Control de Gastos</title>
    <link rel="stylesheet" href="css/normalize.css" />
    <link rel="stylesheet" href="css/styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style></style>
  </head>

  <body>
    <header>Dashboard Control de Gastos</header>

    <div class="container-dashboard">
      <!-- TARJETAS -->

      <div class="cards">
        <div class="card">
          <h3>Total Gastos</h3>
          <p id="total">$0</p>
        </div>

        <div class="card">
          <h3>Registros</h3>
          <p id="registros">0</p>
        </div>
      </div>

      <!-- TABLA -->

      <h3>Historial de gastos</h3>

      <table>
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Tipo</th>
            <th>Concepto</th>
            <th>Monto</th>
          </tr>
        </thead>

        <tbody id="tabla"></tbody>
      </table>

      <br />

      <button onclick="exportExcel()">Exportar Excel</button>
      <button onclick="exportPDF()">Exportar PDF</button>

      <!-- GRAFICA -->

      <div class="chart-box">
        <h3>Gastos por categoría</h3>

        <canvas id="grafica"></canvas>
      </div>
    </div>

    <script>
      /* DATOS ORIGINALES DEL PROYECTO */

      const expenses = [
        {
          employeeName: "Mantenedor A",
          position: "Mantenedor",
          expenses: [
            { category: "Gasolina", amount: 850 },
            { category: "Viático mantenedor", amount: 500 },
          ],
        },

        {
          employeeName: "Técnico 1",
          position: "Tecnico",
          expenses: [
            { category: "Hotel", amount: 1200 },
            { category: "Viático técnico", amount: 800 },
          ],
        },
      ];

      /* CONVERTIR A HISTORIAL */

      let historial = [];

      expenses.forEach((e) => {
        e.expenses.forEach((g) => {
          historial.push({
            empleado: e.employeeName,
            tipo: e.position,
            categoria: g.category,
            monto: g.amount,
          });
        });
      });

      /* TABLA */

      const tabla = document.getElementById("tabla");

      let total = 0;

      historial.forEach((g) => {
        total += g.monto;

        tabla.innerHTML += `

<tr>

<td>${g.empleado}</td>
<td>${g.tipo}</td>
<td>${g.categoria}</td>
<td>$${g.monto}</td>

</tr>

`;
      });

      document.getElementById("total").innerText = "$" + total;
      document.getElementById("registros").innerText = historial.length;

      /* GRAFICA */

      const datos = {};

      historial.forEach((g) => {
        if (!datos[g.categoria]) datos[g.categoria] = 0;

        datos[g.categoria] += g.monto;
      });

      new Chart(document.getElementById("grafica"), {
        type: "bar",

        data: {
          labels: Object.keys(datos),
          datasets: [
            {
              label: "Monto",
              data: Object.values(datos),
            },
          ],
        },
      });

      /* EXPORTAR EXCEL */

      function exportExcel() {
        let ws = XLSX.utils.json_to_sheet(historial);

        let wb = XLSX.utils.book_new();

        XLSX.utils.book_append_sheet(wb, ws, "Gastos");

        XLSX.writeFile(wb, "historial_gastos.xlsx");
      }

      /* EXPORTAR PDF */

      function exportPDF() {
        const { jsPDF } = window.jspdf;

        let doc = new jsPDF();

        doc.text("Historial de gastos", 10, 10);

        let y = 20;

        historial.forEach((g) => {
          doc.text(`${g.empleado} - ${g.categoria} - $${g.monto}`, 10, y);

          y += 10;
        });

        doc.save("historial_gastos.pdf");
      }
    </script>
  </body>
</html>
