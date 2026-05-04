const fs = require('fs');
const path = 'public/assets/js/quotation_page_inline.js';
const s = fs.readFileSync(path,'utf8');
let counts = {paren:0,brack:0,brace:0};
for(let i=0;i<s.length;i++){
  const c = s[i];
  if(c === '(') counts.paren++;
  else if(c === ')') counts.paren--;
  else if(c === '[') counts.brack++;
  else if(c === ']') counts.brack--;
  else if(c === '{') counts.brace++;
  else if(c === '}') counts.brace--;
}
console.log('counts',counts);
// Also try parsing by wrapping in function and using new Function to check syntax
try{
  new Function(fs.readFileSync(path,'utf8'));
  console.log('new Function: OK');
}catch(e){
  console.error('new Function error:', e && e.message);
}
