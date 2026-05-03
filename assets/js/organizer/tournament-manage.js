document.addEventListener("DOMContentLoaded", () => {
    const deleteBtn = document.getElementById("btnDeleteTournament");
    const deleteForm = document.getElementById("formDeleteTournament");
    
    if (deleteBtn && deleteForm) {
        deleteBtn.addEventListener("click", () => {
            // Kullanıcı sil butonuna basınca standart bir onay istenir
            const confirmDelete = confirm("Are you sure you want to delete this tournament? It will be moved to the trash.");
            
            if (confirmDelete) {
                // Onay verilirse PHP dosyasına formu post et
                deleteForm.submit();
            }
        });
    }
});