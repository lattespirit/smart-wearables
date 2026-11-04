{{- define "sw.fullname" -}}
{{- printf "%s" .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "sw.labels" -}}
app.kubernetes.io/name: {{ include "sw.fullname" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}
