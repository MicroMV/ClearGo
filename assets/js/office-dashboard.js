function openFacilitiesItemModal(clearanceId) {
    document.getElementById('facilities-item-clearance').value = clearanceId;
    document.getElementById('facilities-item-modal').classList.add('open');
}

function closeFacilitiesItemModal() {
    document.getElementById('facilities-item-modal').classList.remove('open');
}

const facilitiesItemModal = document.getElementById('facilities-item-modal');
facilitiesItemModal.addEventListener('click', function (event) {
    if (event.target === facilitiesItemModal) {
        closeFacilitiesItemModal();
    }
});
