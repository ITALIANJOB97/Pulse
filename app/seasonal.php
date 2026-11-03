<?php
declare(strict_types=1);

/** Gregorian Easter, without requiring the optional PHP calendar extension. */
function easterSunday(int $year): DateTimeImmutable {
    $a=$year%19;$b=intdiv($year,100);$c=$year%100;
    $d=intdiv($b,4);$e=$b%4;$f=intdiv($b+8,25);$g=intdiv($b-$f+1,3);
    $h=(19*$a+$b-$d-$g+15)%30;$i=intdiv($c,4);$k=$c%4;
    $l=(32+2*$e+2*$i-$h-$k)%7;$m=intdiv($a+11*$h+22*$l,451);
    $month=intdiv($h+$l-7*$m+114,31);$day=($h+$l-7*$m+114)%31+1;
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day),new DateTimeZone('Europe/Zurich'));
}
function validateCarnivalDates(string $start,string $end): array {
    if($start===''&&$end==='')return [];
    $a=DateTimeImmutable::createFromFormat('!Y-m-d',$start,new DateTimeZone('Europe/Zurich'));
    $b=DateTimeImmutable::createFromFormat('!Y-m-d',$end,new DateTimeZone('Europe/Zurich'));
    if(!$a||!$b||$a->format('Y-m-d')!==$start||$b->format('Y-m-d')!==$end||$a>$b||$a->format('Y')!==$b->format('Y')||$a->diff($b)->days>60)throw new DomainException(t('carnival_invalid'));
    return ['start'=>$start,'end'=>$end];
}
function seasonalState(DateTimeImmutable $now,bool $enabled=true,array $carnival=[]): array {
    $zone=new DateTimeZone('Europe/Zurich');$now=$now->setTimezone($zone);
    $windows=[];$year=(int)$now->format('Y');
    foreach(range($year-1,$year+1) as $y){
        $easter=easterSunday($y);
        // Swiss Fasnacht reference: Schmutziger Donnerstag through Güdisdienstag.
        $carnivalStart=$easter->modify('-52 days');$carnivalEnd=$easter->modify('-46 days');
        if(isset($carnival['start'],$carnival['end'])&&substr($carnival['start'],0,4)===(string)$y){
            $carnivalStart=new DateTimeImmutable($carnival['start'],$zone);
            $carnivalEnd=(new DateTimeImmutable($carnival['end'],$zone))->modify('+1 day');
        }
        foreach([
            ['halloween',new DateTimeImmutable("$y-10-20",$zone),new DateTimeImmutable("$y-11-06",$zone)],
            ['christmas',new DateTimeImmutable("$y-12-01",$zone),new DateTimeImmutable(($y+1).'-01-07',$zone)],
            ['easter',$easter->modify('-7 days'),$easter->modify('+2 days')],
            ['carnival',$carnivalStart,$carnivalEnd],
        ] as [$key,$start,$end])$windows[]=['key'=>$key,'starts_at'=>$start->getTimestamp(),'ends_at'=>$end->getTimestamp()];
    }
    return ['enabled'=>$enabled,'windows'=>$windows];
}
