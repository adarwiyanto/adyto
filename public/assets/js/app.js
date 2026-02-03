function toggleSidebar(){
  const el = document.getElementById('sidebar');
  if(!el) return;
  el.classList.toggle('hidden');
}

function goBack(defaultUrl){
  if(window.history.length > 1){
    window.history.back();
    return;
  }
  if(defaultUrl){
    window.location.href = defaultUrl;
  }
}