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
use JinDistill\Composition\CompositionConflict;
use JinDistill\Exceptions\UndiffableDefinitionException;
final class Differ {
 public function diff(ComposedDocument $parent, ComposedDocument $target, string $reference): DiffResult {
  $this->guardOpaqueOverrides($parent,$target);
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

 private function guardOpaqueOverrides(ComposedDocument $parent, ComposedDocument $target): void
 {
  $conflicts = [];
  foreach ($this->assignments($parent) as $ancestor) {
   if ($ancestor->value()->isStaticallyKnown()) {
    continue;
   }
   foreach ($this->assignments($target) as $descendant) {
    if ($this->isAncestor($ancestor->path()->segments(), $descendant->path()->segments())) {
     $conflicts[] = new CompositionConflict($ancestor->path()->toJsonPointer(), $descendant->path()->toJsonPointer());
    }
   }
  }
  if ($conflicts !== []) {
   throw new UndiffableDefinitionException($conflicts);
  }
 }

 /** @return list<Assignment> */
 private function assignments(ComposedDocument $document): array
 {
  $assignments = [];
  foreach ($document->document()->statements() as $statement) {
   if ($statement instanceof Assignment && !str_starts_with($statement->path()->segments()[0], '--')) {
    $assignments[] = $statement;
   }
  }
  return $assignments;
 }

 /**
  * @param list<string> $ancestor
  * @param list<string> $descendant
  */
 private function isAncestor(array $ancestor, array $descendant): bool
 {
  return count($descendant) > count($ancestor) && array_slice($descendant, 0, count($ancestor)) === $ancestor;
 }
}
