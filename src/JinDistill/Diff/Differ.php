<?php
namespace JinDistill\Diff;
use JinDistill\Composition\ComposedDocument;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;
final class Differ {
 public function diff(ComposedDocument $parent, ComposedDocument $target, string $reference): DiffResult {
  $differences=(new DefinitionDiffer())->compare($parent,$target); $removals=(new RemovalPlanner())->plan($parent,$target,$differences);
  $first=$target->document()->statements()[0]; $span=$first->span(); $source=$target->document()->source();
  $nodes=[new Assignment(Path::fromSegments(['--extends']),new Value($reference,ValueKind::Scalar,$reference,true),[],null,$span)];
  foreach($differences->all() as $d) if(in_array($d->kind(),[DifferenceKind::Added,DifferenceKind::Changed,DifferenceKind::MetadataChanged],true)) $nodes[]=$d->target();
  return new DiffResult((new JinRenderer())->render(new Document($nodes,$source)),$differences,$removals);
 }
}
