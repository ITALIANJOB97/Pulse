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
function seasonalState(DateTimeImmutable $now,bool $enabled=true): array {
    $zone=new DateTimeZone('Europe/Zurich');$now=$now->setTimezone($zone);
    $windows=[];$year=(int)$now->format('Y');
    foreach(range($year-1,$year+1) as $y){
        $easter=easterSunday($y);
        foreach([
            ['halloween',new DateTimeImmutable("$y-10-20",$zone),new DateTimeImmutable("$y-11-02",$zone)],
            ['christmas',new DateTimeImmutable("$y-12-01",$zone),new DateTimeImmutable(($y+1).'-01-07',$zone)],
            ['easter',$easter->modify('-7 days'),$easter->modify('+2 days')],
        ] as [$key,$start,$end])$windows[]=['key'=>$key,'starts_at'=>$start->getTimestamp(),'ends_at'=>$end->getTimestamp()];
    }
    return ['enabled'=>$enabled,'windows'=>$windows];
}
