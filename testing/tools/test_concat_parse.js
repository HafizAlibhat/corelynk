const fs=require('fs');
const a = fs.readFileSync('public/assets/js/quotation_page_inline.js','utf8');
const b = fs.readFileSync('app/Views/quotations/create.php','utf8');
// Extract the inline <script> ... </script> block inserted near the end of create.php
const m = b.match(/<script>[\s\S]*?<\/script>/m);
const inline = m ? m[0].replace(/^<script>/,'').replace(/<\/script>$/,'') : '';
try{
  new Function(a+"\n/* inline */\n"+inline);
  console.log('Combined script parsed OK');
}catch(e){
  console.error('Parse error:', e && e.message);
}
