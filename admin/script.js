let menu_state=false;
let menu_open_btn = document.querySelectorAll(".menu-open-btn");
let menu_bar_view = document.querySelector(".menu-bar-view");

menu_open_btn.forEach((btn) => {
  btn.addEventListener("click", () =>{
    menu_bar_view.classList.toggle("show");
    
    // For desktop, if we want to push content, we can toggle a class on the wrapper
    // But currently the sidebar is fixed, so let's stick to mobile toggle first.
    if(window.innerWidth > 991) {
       // Optional: toggle a 'collapsed' class if we want a narrow sidebar on desktop
    }
  });
});


function LogoutAccount(){
  if (confirm("Are you sure want to Logout?")) {
    window.location.href = "logout-account";
  }
}

function exportPDF(pdfName, tableId) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const table = document.getElementById(tableId);
    const data = [];

    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12 || 12;
    const formattedTime = `${String(hours).padStart(2, '0')}-${minutes}-${seconds}_${ampm}`;
    const formattedDate = `${year}-${month}-${day}`;
    const timestamp = `${formattedDate}_${formattedTime}`;

    table.querySelectorAll("tr").forEach(row => {
        const rowData = [];
        row.querySelectorAll("td, th").forEach(cell => {
            let text = cell.innerText.trim();
            text = text.replace(/₹/g, 'Rs');
            rowData.push(text);
        });
        data.push(rowData);
    });

    doc.autoTable({
        head: [data[0]],
        body: data.slice(1)
    });

    doc.save(`${pdfName}_${timestamp}.pdf`);
}