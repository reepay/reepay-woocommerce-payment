function showYoutubeVideo(){
    if(document.getElementById("youtubeGuide").style.visibility == "visible"){
        document.getElementById("youtubeGuide").style.visibility = "collapse";
        document.getElementById("youtubeWrapper").style.visibility = "collapse";
    } else {
        document.getElementById("youtubeGuide").style.visibility = "visible";
        document.getElementById("youtubeWrapper").style.visibility = "visible";
    }
} 
function hideYoutubeVideo(){
    if(document.getElementById("youtubeWrapper").style.visibility == "visible"){
        document.getElementById("youtubeGuide").style.visibility = "collapse";
        document.getElementById("youtubeWrapper").style.visibility = "collapse";
    }    
}