<?php
declare(strict_types=1);
/** Server-authored static SVG (not arbitrary user-uploaded SVG); text is XML-escaped. */
function make_intro_svg(array $data):string {
 $sanitize=static fn(string $s):string=>trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s)??'');
 $title=mb_substr($sanitize((string)($data['title']??'')),0,90);
 if($title==='')throw new InvalidArgumentException('Escribe un título para la introducción');
 $subtitle=mb_substr($sanitize((string)($data['subtitle']??'')),0,140);
 $brand=mb_substr($sanitize((string)($data['brand']??'SECTOR SIGN')),0,64);
 $call=mb_substr($sanitize((string)($data['call_to_action']??'DESCUBRE MÁS')),0,70);
 $palette=(string)($data['palette']??'navy');
 $colors=['navy'=>['#081b38','#1763e0','#d6eaff'],'emerald'=>['#043c3c','#18aa88','#cbfff1'],'sunset'=>['#652052','#f45e63','#ffe8b2'],'night'=>['#090909','#383838','#eaeaea']];
 if(!isset($colors[$palette]))throw new InvalidArgumentException('Paleta inválida');
 [$start,$end,$light]=$colors[$palette];
 // Pure SVG text: SVG <foreignObject> does not render consistently in WebView <img>.
 $wrap=static function(string $value,int $perLine=26,int $maxLines=3):array {
  $result=[];$words=preg_split('/\s+/u',$value,-1,PREG_SPLIT_NO_EMPTY)?:[];$line='';
  foreach($words as $word){
   while(mb_strlen($word)>$perLine){if($line!==''){$result[]=$line;$line='';}$result[]=mb_substr($word,0,$perLine);$word=mb_substr($word,$perLine);}
   $candidate=$line===''?$word:$line.' '.$word;
   if(mb_strlen($candidate)>$perLine&&$line!==''){$result[]=$line;$line=$word;}else{$line=$candidate;}
  }
  if($line!=='')$result[]=$line;
  if(count($result)>$maxLines){$result=array_slice($result,0,$maxLines);$result[$maxLines-1]=mb_substr($result[$maxLines-1],0,$perLine-1).'…';}
  return $result;
 };
 $safe=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_XML1|ENT_SUBSTITUTE,'UTF-8');
 $lines=$wrap($title);$ts='';foreach($lines as $i=>$line){$y=478+$i*127;$ts.='<tspan x="140" y="'.$y.'">'.$safe($line).'</tspan>';}
 $subtitle=$safe(mb_substr($subtitle,0,85));$brand=$safe(mb_substr($brand,0,26));$call=$safe(mb_substr($call,0,28));
 return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080" viewBox="0 0 1920 1080">
 <defs><linearGradient id="bg" x2="1" y2="1"><stop stop-color="{$start}"/><stop offset="1" stop-color="{$end}"/></linearGradient></defs>
 <rect width="1920" height="1080" fill="url(#bg)"/>
 <circle cx="1670" cy="130" r="400" fill="#ffffff" opacity=".07"/>
 <circle cx="1740" cy="890" r="300" fill="#ffffff" opacity=".06"/>
 <path d="M140 285h1650" stroke="#ffffff" stroke-opacity=".25" stroke-width="2"/>
 <rect x="138" y="124" width="600" height="94" rx="26" fill="#ffffff" fill-opacity=".17"/>
 <text x="180" y="186" font-family="Arial,Helvetica,sans-serif" fill="{$light}" font-size="45" font-weight="700">{$brand}</text>
 <text font-family="Arial,Helvetica,sans-serif" fill="#ffffff" font-size="100" font-weight="900">{$ts}</text>
 <text x="140" y="817" font-family="Arial,Helvetica,sans-serif" fill="{$light}" font-size="48">{$subtitle}</text>
 <rect x="140" y="884" width="740" height="120" rx="35" fill="white"/>
 <text x="185" y="962" font-family="Arial,Helvetica,sans-serif" fill="{$start}" font-size="42" font-weight="800">{$call}</text>
</svg>
SVG;
}
/** Strictly external HTTPS playable media. Browsers fetch streams directly, never from PHP. */
function validate_stream_url(string $url):bool {
 if(strlen($url)>1500||!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https')return false;
 if(parse_url($url,PHP_URL_USER)!==null||parse_url($url,PHP_URL_PASS)!==null)return false;
 $host=(string)parse_url($url,PHP_URL_HOST);if(!$host||preg_match('/(?:^localhost$|\.localhost$|\.local$|\.internal$)/i',$host))return false;
 if(filter_var($host,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
 return (bool)preg_match('/\.(?:m3u8|mp4)(?:\?|$)/i',$url);
}
