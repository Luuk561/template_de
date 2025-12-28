@props(['data' => []])

@if(isset($data['enabled']) && $data['enabled'] && !empty($data['columns']) && !empty($data['rows']))
<div class="mt-8">
  <h3 class="text-lg font-semibold text-gray-900 mb-4">Snelle vergelijking</h3>
  
  <div class="overflow-x-auto">
    <table class="w-full bg-white border border-gray-200 rounded-xl shadow-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 border-b border-gray-200">
            Eigenschap
          </th>
          @foreach($data['columns'] as $column)
            @if(isset($column['label']) && $column['label'])
              <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 border-b border-gray-200">
                {{ $column['label'] }}
              </th>
            @endif
          @endforeach
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        @foreach($data['rows'] as $row)
          @if(isset($row['feature']) && $row['feature'])
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900">
                {{ $row['feature'] }}
              </td>
              @if(isset($row['a']))
                <td class="px-4 py-3 text-sm text-gray-800 text-center">
                  {{ $row['a'] }}
                </td>
              @endif
              @if(isset($row['b']))
                <td class="px-4 py-3 text-sm text-gray-800 text-center">
                  {{ $row['b'] }}
                </td>
              @endif
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif