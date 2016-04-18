jQuery(document).ready(function($){ 
    //return if the clrbase object does not exist
    if(typeof clrbase == "undefined") return;

    if (clrbase.folder.allow_nesting) {
      if ('upload' == clrbase.screen.id) {
        $addMedia = $('h1 a.page-title-action').first();
        $addMedia.text('New Media');
        $addFolder = $('<a href="#" class="addnew add-new-h2 folders">' + clrbase.l10n.newFolder + '</a>').insertAfter($addMedia)
      } else {

      }
    }
});