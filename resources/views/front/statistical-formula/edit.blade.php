@php
$reportDetail = $reportDetail ?? null;
$formulas = $reportDetail ? $reportDetail->statisticalFormulas : collect();
@endphp

<div class="mb-3">
    <h6 class="text-primary">
        <i class="fas fa-chart-bar me-2"></i>{{ $reportDetail->name ?? 'Báo cáo' }}
    </h6>
    <small class="text-muted">{{ $reportDetail->reportGroup->name ?? '' }}</small>
</div>

@if($formulas->count() > 0)
    @foreach($formulas as $index => $formula)
    <div class="formula-row border rounded p-3 mb-3" data-index="{{ $index }}">
        <div class="row">
            <div class="col-md-4">
                <label class="form-label">Tiêu chí</label>
                <input type="text" class="form-control" name="formulas[{{ $index }}][criteria]" 
                       value="{{ $formula->criteria }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Công thức</label>
                <input type="text" class="form-control" name="formulas[{{ $index }}][formula]" 
                       value="{{ $formula->formula }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mô tả</label>
                <textarea class="form-control" name="formulas[{{ $index }}][description]" rows="2">{{ $formula->description }}</textarea>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeFormulaRow({{ $index }})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="formulas[{{ $index }}][is_active]" 
                           value="1" {{ $formula->is_active ? 'checked' : '' }}>
                    <label class="form-check-label">Sử dụng công thức này</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Thứ tự</label>
                <input type="number" class="form-control" name="formulas[{{ $index }}][sort_order]" 
                       value="{{ $formula->sort_order }}" min="0">
            </div>
        </div>
    </div>
    @endforeach
@else
    <div class="text-center text-muted py-4">
        <i class="fas fa-calculator fa-3x mb-3"></i>
        <p>Chưa có công thức nào cho báo cáo này.</p>
        <p>Nhấn "Thêm công thức" để bắt đầu.</p>
    </div>
@endif
