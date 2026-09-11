<?php
namespace JinDistill\Diff;
use JinDistill\Composition\ComposedDocument;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;
use JinDistill\Syntax\Section;
final class Differ {
 public function diff(ComposedDocument $parent, ComposedDocument $target, string $reference): DiffResult {
  $differences=(new DefinitionDiffer())->compare($parent,$target); $removals=(new RemovalPlanner())->plan($parent,$target,$differences);
  $first=$target->document()->statements()[0]; $span=$first->span(); $source=$target->document()->source();
  $nodes=[new Assignment(Path::fromSegments(['--extends']),new Value($reference,ValueKind::Scalar,$reference,true),[],null,$span)];
  if($removals!==[]) $nodes[]=new Assignment(Path::fromSegments(['--without']),new Value('',ValueKind::Json,array_map(static fn($path)=>implode('.',$path->segments()),$removals),true),[],null,$span);
  $section=[];
  foreach($differences->all() as $d) if(in_array($d->kind(),[DifferenceKind::Added,DifferenceKind::Changed,DifferenceKind::MetadataChanged],true)) {
   $assignment=$d->target(); $next=count($assignment->path()->segments())>1?array_slice($assignment->path()->segments(),0,-1):[];
   if($next!==[]&&$next!==$section) $nodes[]=new Section(Path::fromSegments($next),implode('.',$next),$assignment->span());
   $section=$next; foreach($assignment->comments() as $comment) $nodes[]=$comment; $nodes[]=$assignment;
  }
  return new DiffResult((new JinRenderer())->render(new Document($nodes,$source)),$differences,$removals);
 }
}
