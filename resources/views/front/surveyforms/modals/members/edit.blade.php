@include('front.surveyforms.modals.members._form', [
  // ngữ cảnh & submit
  'survey'       => $survey,
  'action'       => route('front.surveyforms.members.update', [$detail->id]),
  'method'       => 'PUT',
  'submitLabel'  => 'Cập nhật',

  // dữ liệu (prefill)
  'person'       => $person,
  'education'    => $education,
  'literacy'     => $literacy,
  'disability'   => $disability,
  'special'      => $special,
  'membership'   => $membership,

  // danh mục
  'ethnicities'  => $ethnicities,
  'religions'    => $religions,
  'resTypes'     => $resTypes,
  'resStats'     => $resStats,
  'blocks'       => $blocks,
  'gradLevels'   => $gradLevels,
  'vocLevels'    => $vocLevels,
  'grades'       => $grades,
  'disCats'      => $disCats,
  'specials'     => $specials,
  'priorities'   => $priorities,

  // school picker
  'initialSchool'=> $initialSchool,
  'geoInit'      => $geoInit,   // và trong _form nhớ truyền 'geo' => $geoInit cho partial school-picker2
])
