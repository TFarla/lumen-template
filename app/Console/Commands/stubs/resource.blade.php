namespace App\Resources;


use Illuminate\Http\Resources\Json\Resource;

class {{$pascalCase}} extends Resource
{
    public function toArray($request)
    {
        return [
            @php
                /** @var \App\Console\Commands\DataType $dataType */
            @endphp
            @foreach ($dataTypes as $dataType)
                @if ($dataType->isDate())
                    '{{camel_case($dataType->getName())}}' => $this->{{$dataType->getName()}}->toISO8601String(),
                @elseif ($dataType->isBoolean())
                    '{{camel_case($dataType->getName())}}' => (bool)$this->{{$dataType->getName()}},
                @else
                    '{{camel_case($dataType->getName())}}' => $this->{{$dataType->getName()}},
                @endif
            @endforeach
            'createdAt' => $this->when($this->created_at, function() {
                return $this->created_at->toISO8601String();
            }, null),
            'updatedAt' => $this->when($this->updated_at, function() {
                return $this->updated_at->toISO8601String();
            }, null)
        ];
    }
}