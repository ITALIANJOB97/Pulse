const assert=require('node:assert/strict');
const {awardPeople,hallPool,createHallSelector}=require('../public/assets/people.js');
const employees=[{id:1,name:'One'},{id:2,name:'Two'},{id:3,name:'Three'}];
assert.deepEqual(awardPeople({employee_id:1},employees),[employees[0]]);
assert.deepEqual(awardPeople({employee_ids:[2,1]},employees),[employees[1],employees[0]]);
assert.equal(awardPeople({employee_ids:[1,1,2,3]},employees).length,2);
assert.deepEqual(awardPeople({},employees),[]);
assert.equal(awardPeople({employee_ids:[99,1]},employees)[0].id,1);
const old={id:1,added_at:10,priority_until:50},fresh={id:2,added_at:100,priority_until:200},fresh2={id:3,added_at:110,priority_until:210};
assert.deepEqual(hallPool([old,fresh],99),[old,fresh]);
assert.deepEqual(hallPool([old,fresh],100),[fresh]);
assert.deepEqual(hallPool([old,fresh],199.999),[fresh]);
assert.deepEqual(hallPool([old,fresh],200),[old,fresh]);
assert.deepEqual(hallPool([old,fresh,fresh2],150),[fresh,fresh2]);
assert.deepEqual(hallPool([old,fresh,fresh2],200),[fresh2]);
let seed=73;const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
const pick=createHallSelector(random);assert.equal(pick([],150),null);
for(let i=0;i<20;i++)assert.equal(pick([old,fresh],150).id,2);
let previous=2;
for(let i=0;i<20;i++){const p=pick([old,fresh,fresh2],150);assert.notEqual(p.id,previous);assert([2,3].includes(p.id));previous=p.id;}
for(let cycle=0;cycle<20;cycle++){
 const ids=[];for(let i=0;i<3;i++){const p=pick([old,fresh,fresh2],211);assert.notEqual(p.id,previous);previous=p.id;ids.push(p.id);}
 assert.equal(new Set(ids).size,3,'Every member appears once per random cycle');
}
assert.equal(pick([fresh2],212).id,3);assert.equal(pick([fresh2],213).id,3);assert.equal(pick([],214),null);
assert.equal(pick([{...fresh2,name:'Edited'}],215).name,'Edited');
console.log('People selection passed: legacy and dual awards, seven-day boundaries, multiple newcomers, full random cycles, no consecutive repeats, removal and edits.');
