import "../../../../style/global.css";

import React, { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator, FlatList, Modal, Platform, Pressable, ScrollView,
  StyleSheet, Text, TextInput, View, useColorScheme, useWindowDimensions,
} from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { Mainframe } from "../../../../components/NavBar";
import { router } from "expo-router";
import { getLastEventoId, getLastEventoNome } from "@/app/utils/lastEvento";
import AsyncStorage from "@react-native-async-storage/async-storage";

/** SICAD · envio filtrado e seleção explícita. Substitui somente a tela send.tsx. */
const API_BASE = "https://sicad.linceonline.com.br/controller";
const PAGE_SIZE = 50;
const INTERVALO_ENVIO_MS = 250;
type IoniconName = React.ComponentProps<typeof Ionicons>["name"];
type Registro = Record<string, unknown>;
type Certificado = {
  cod_certificado: number;
  codigo_validacao?: string | null;
  usuario_id?: number;
  participante: string;
  email: string;
  status: number;
  atividade_id?: number;
  atividade: string;
};
type Filtros = {
  busca: string;
  participante: string;
  atividade: string;
  status: "todos" | "pendentes" | "enviados";
};
type Ordem = { campo: "nome" | "atividade"; direcao: "asc" | "desc" };
type Modo = "email" | "download";
type Escopo = "filtrados" | "selecionados" | "individual";
type Opcao = { value: string; label: string; detalhe?: string; count?: number };
type Confirmacao = { modo: Modo; escopo: Escopo; itens: Certificado[]; descricao: string };
type ResultadoItem = { certificado: Certificado; ok: boolean; incerto: boolean; erro?: string };
type ResultadoFila = {
  total: number; itens: ResultadoItem[]; naoProcessados: number; interrompido: boolean;
};
type Relatorio = ResultadoFila & { modo: Modo; descricao: string };
const FILTROS_INICIAIS: Filtros = { busca: "", participante: "", atividade: "", status: "todos" };

// INICIO_LOGICA_TESTAVEL — sem React, DOM ou acesso ao servidor.
function ehRegistro(valor: unknown): valor is Registro {
  return !!valor && typeof valor === "object" && !Array.isArray(valor);
}
function idPositivo(valor: unknown): number | undefined {
  if (typeof valor !== "number" && typeof valor !== "string") return undefined;
  if (typeof valor === "string" && !/^\d+$/.test(valor.trim())) return undefined;
  const n = Number(valor);
  return Number.isSafeInteger(n) && n > 0 ? n : undefined;
}
function textoBusca(valor: string): string {
  return valor.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("pt-BR").trim();
}
function sucessoApi(valor: unknown): boolean {
  return valor === true || valor === 1 || valor === "1";
}
function emailValido(valor: string): boolean {
  // Triagem de formato, não comprovação de existência/entrega da caixa postal.
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor.trim());
}
function normalizarCertificados(data: unknown): { lista: Certificado[]; descartados: number } {
  if (ehRegistro(data) && "success" in data && !sucessoApi(data.success)) {
    throw new Error(String(data.message || "Não foi possível consultar os certificados."));
  }
  const origem = Array.isArray(data) ? data : ehRegistro(data) ? data.certificados : null;
  if (!Array.isArray(origem)) throw new Error("A API não retornou uma lista de certificados válida.");
  const lista: Certificado[] = [];
  const vistos = new Set<number>();
  let descartados = 0;
  for (const item of origem) {
    if (!ehRegistro(item)) { descartados++; continue; }
    const id = idPositivo(item.cod_certificado);
    if (!id || vistos.has(id)) { descartados++; continue; }
    vistos.add(id);
    lista.push({
      cod_certificado: id,
      codigo_validacao: item.codigo_validacao ? String(item.codigo_validacao) : null,
      // Nunca usar um "id" genérico: ele pode ser o ID do certificado.
      usuario_id: idPositivo(item.usuario_id ?? item.user_id ?? item.participante_id ?? item.fk_Usuario_ID),
      participante: String(item.participante ?? "").trim(),
      email: String(item.email ?? "").trim(),
      status: Number(item.status ?? 0) === 1 ? 1 : 0,
      atividade_id: idPositivo(item.atividade_id),
      atividade: String(item.atividade ?? "").trim(),
    });
  }
  return { lista, descartados };
}
function chaveParticipante(c: Certificado): string {
  if (c.usuario_id) return `usuario:${c.usuario_id}`;
  // Compatibilidade com a listagem antiga, que não expunha usuario_id.
  // Não agrupar só por nome, nem só por e-mail. Não remover acentos da identidade.
  if (c.participante && c.email) return `cadastro:${JSON.stringify([c.participante, c.email.toLowerCase()])}`;
  return `certificado:${c.cod_certificado}`;
}
function chaveAtividade(c: Certificado): string {
  if (c.atividade_id) return `atividade:${c.atividade_id}`;
  return c.atividade ? `titulo:${c.atividade}` : `certificado:${c.cod_certificado}`;
}
const comparador = new Intl.Collator("pt-BR", { sensitivity: "base", numeric: true });
function opcoesAgrupadas(lista: Certificado[], tipo: "participante" | "atividade"): Opcao[] {
  const mapa = new Map<string, Opcao>();
  for (const c of lista) {
    const participante = tipo === "participante";
    const key = participante ? chaveParticipante(c) : chaveAtividade(c);
    const anterior = mapa.get(key);
    if (anterior) { anterior.count = (anterior.count || 0) + 1; continue; }
    mapa.set(key, {
      value: key,
      label: (participante ? c.participante : c.atividade) || "Não informado",
      detalhe: participante
        ? `${c.email || "Sem e-mail"}${c.usuario_id ? ` · usuário #${c.usuario_id}` : " · sem ID de usuário"}`
        : c.atividade_id ? `Atividade #${c.atividade_id}` : "ID da atividade não informado",
      count: 1,
    });
  }
  return [...mapa.values()].sort((a, b) => comparador.compare(a.label, b.label) || comparador.compare(a.detalhe || "", b.detalhe || "") || a.value.localeCompare(b.value));
}
function filtrarOrdenar(lista: Certificado[], filtros: Filtros, ordem: Ordem): Certificado[] {
  const termos = textoBusca(filtros.busca).split(/\s+/).filter(Boolean);
  return lista.filter(c => {
    if (filtros.participante && chaveParticipante(c) !== filtros.participante) return false;
    if (filtros.atividade && chaveAtividade(c) !== filtros.atividade) return false;
    if (filtros.status === "enviados" && c.status !== 1) return false;
    if (filtros.status === "pendentes" && c.status === 1) return false;
    const texto = textoBusca(`${c.participante} ${c.email} ${c.atividade} ${c.codigo_validacao || ""} ${c.cod_certificado}`);
    return termos.every(termo => texto.includes(termo));
  }).sort((a, b) => {
    const primario = ordem.campo === "nome" ? comparador.compare(a.participante, b.participante) : comparador.compare(a.atividade, b.atividade);
    if (primario) return primario * (ordem.direcao === "asc" ? 1 : -1);
    const secundario = ordem.campo === "nome" ? comparador.compare(a.atividade, b.atividade) : comparador.compare(a.participante, b.participante);
    return secundario || comparador.compare(a.email, b.email) || a.cod_certificado - b.cod_certificado;
  });
}
function alvoSelecionado(filtrados: Certificado[], selecionados: ReadonlySet<number>): Certificado[] {
  // Conjunto vazio NUNCA significa "todos". Certificados ocultos nunca entram no lote.
  return filtrados.filter(c => selecionados.has(c.cod_certificado));
}
function planejarOperacao(itens: Certificado[], modo: Modo, incluirEnviados: boolean) {
  const vistos = new Set<number>();
  const unicos = itens.filter(c => {
    if (!idPositivo(c.cod_certificado) || vistos.has(c.cod_certificado)) return false;
    vistos.add(c.cod_certificado); return true;
  });
  const jaEnviados = modo === "email" && !incluirEnviados ? unicos.filter(c => c.status === 1) : [];
  const candidatos = unicos.filter(c => modo !== "email" || incluirEnviados || c.status !== 1);
  const semEmail = modo === "email" ? candidatos.filter(c => !emailValido(c.email)) : [];
  const enviar = candidatos.filter(c => modo !== "email" || emailValido(c.email));
  return {
    enviar, jaEnviados, semEmail,
    destinatarios: new Set(enviar.map(c => c.email.toLowerCase())).size,
    atividades: new Set(enviar.map(chaveAtividade)).size,
  };
}
class ErroApi extends Error {
  constructor(message: string, public incerto = false, public interromper = false) {
    super(message); this.name = "ErroApi";
  }
}
async function executarFila(
  origem: Certificado[], executar: (certificado: Certificado) => Promise<void>,
  opcoes: {
    deveParar: () => boolean;
    aoIniciar?: (certificado: Certificado, indice: number) => void;
    aoConcluir?: (item: ResultadoItem) => void;
    intervaloMs?: number;
  },
): Promise<ResultadoFila> {
  // Fotografia do lote: atualizações de status não removem itens ainda não processados.
  const vistos = new Set<number>();
  const fila = origem.filter(c => {
    if (!idPositivo(c.cod_certificado) || vistos.has(c.cod_certificado)) return false;
    vistos.add(c.cod_certificado); return true;
  }).map(c => ({ ...c }));
  const itens: ResultadoItem[] = [];
  let interrompido = false;
  for (let indice = 0; indice < fila.length; indice++) {
    if (opcoes.deveParar()) { interrompido = true; break; }
    const certificado = fila[indice];
    opcoes.aoIniciar?.(certificado, indice);
    let resultado: ResultadoItem;
    let parar = false;
    try {
      await executar(certificado);
      resultado = { certificado, ok: true, incerto: false };
    } catch (erro) {
      resultado = {
        certificado, ok: false, incerto: erro instanceof ErroApi && erro.incerto,
        erro: erro instanceof Error ? erro.message : "Falha desconhecida.",
      };
      parar = erro instanceof ErroApi && (erro.interromper || erro.incerto);
    }
    itens.push(resultado);
    opcoes.aoConcluir?.(resultado);
    if (parar) { interrompido = true; break; }
    if (indice + 1 < fila.length && (opcoes.intervaloMs ?? INTERVALO_ENVIO_MS) > 0) {
      await new Promise(resolve => setTimeout(resolve, opcoes.intervaloMs ?? INTERVALO_ENVIO_MS));
    }
  }
  return { total: fila.length, itens, naoProcessados: fila.length - itens.length, interrompido };
}
function parseApiResponse(raw: string): unknown {
  const texto = raw.replace(/^\uFEFF/, "").trim();
  try { return JSON.parse(texto); } catch { /* Compatibilidade com avisos PHP antes do JSON. */ }
  const obj = texto.indexOf("{");
  const arr = texto.indexOf("[");
  const inicio = obj < 0 ? arr : arr < 0 ? obj : Math.min(obj, arr);
  if (inicio >= 0) {
    try { return JSON.parse(texto.slice(inicio)); } catch { /* Não é JSON válido. */ }
  }
  return null;
}
function mensagemResposta(data: unknown, fallback: string): string {
  return ehRegistro(data) && typeof data.message === "string" ? data.message : fallback;
}
function nomeArquivo(c: Certificado): string {
  const limpar = (v: string) => textoBusca(v).replace(/[^a-z0-9._-]+/g, "_").replace(/^_+|_+$/g, "").slice(0, 70) || "certificado";
  return `${limpar(c.participante)}_${limpar(c.atividade)}_${c.cod_certificado}.pdf`;
}
// FIM_LOGICA_TESTAVEL

async function postJsonSafe(url: string, body: unknown, signal?: AbortSignal, envioEmail = false): Promise<unknown> {
  const controller = new AbortController();
  const cancelar = () => controller.abort();
  if (signal?.aborted) controller.abort();
  signal?.addEventListener("abort", cancelar, { once: true });
  const timer = setTimeout(cancelar, envioEmail ? 120000 : 45000);
  try {
    const res = await fetch(url, {
      method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body), signal: controller.signal,
    });
    const raw = await res.text();
    const data = parseApiResponse(raw);
    if (!res.ok) {
      // Um HTTP 500 pode ocorrer DEPOIS do SMTP e antes de atualizar o status no banco.
      const incerto = envioEmail && res.status >= 500;
      throw new ErroApi(mensagemResposta(data, `O servidor retornou HTTP ${res.status}.`), incerto,
        incerto || [401, 403, 429].includes(res.status));
    }
    if (data === null) throw new ErroApi("Resposta inválida do servidor. Verifique a sessão e o endpoint.", envioEmail, true);
    return data;
  } catch (erro) {
    if (signal?.aborted) throw erro;
    if (erro instanceof ErroApi) throw erro;
    throw new ErroApi(controller.signal.aborted
      ? "O servidor demorou para responder."
      : "A comunicação com o servidor falhou.", envioEmail, true);
  } finally {
    clearTimeout(timer);
    signal?.removeEventListener("abort", cancelar);
  }
}
async function baixarCertificado(c: Certificado): Promise<void> {
  if (Platform.OS !== "web" || typeof document === "undefined") {
    throw new ErroApi("Abra esta tela no navegador para baixar PDFs.", false, true);
  }
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 120000);
  try {
    const res = await fetch(`${API_BASE}/enviar_certificado.php`, {
      method: "POST", headers: { Accept: "application/pdf, application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ cod_certificado: c.cod_certificado, modo: "download" }), signal: controller.signal,
    });
    if (!res.ok || !(res.headers.get("content-type") || "").toLowerCase().includes("application/pdf")) {
      const data = parseApiResponse(await res.text());
      throw new ErroApi(mensagemResposta(data, "O servidor não retornou um PDF válido."), false, [401, 403, 429].includes(res.status));
    }
    const blob = await res.blob();
    if (!(await blob.slice(0, 1024).text()).includes("%PDF-")) throw new ErroApi("Arquivo recebido sem assinatura PDF válida.");
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    // ID no nome evita colisões entre certificados de pessoas/atividades homônimas.
    link.download = nomeArquivo(c);
    link.style.display = "none";
    document.body.appendChild(link); link.click(); link.remove();
    setTimeout(() => window.URL.revokeObjectURL(url), 60000);
  } catch (erro) {
    if (erro instanceof ErroApi) throw erro;
    throw new ErroApi(controller.signal.aborted ? "Tempo esgotado ao gerar o PDF." : "Falha de comunicação ao baixar o PDF.", false, true);
  } finally { clearTimeout(timer); }
}

const CLARO = {
  bg: "#f5f7fb", surface: "#ffffff", ink: "#17253e", muted: "#65748b", line: "#e1e7f0",
  accent: "#225fe5", soft: "#eef4ff", green: "#14775e", greenBg: "#eaf7f0", amber: "#966118", amberBg: "#fff7e8",
  danger: "#b12b44", dangerBg: "#fff0f3", navy: "#192e53",
};
type Tema = typeof CLARO;
const ESCURO: Tema = {
  bg: "#101827", surface: "#182438", ink: "#edf2fc", muted: "#abb9cf", line: "#33415a",
  accent: "#94b9ff", soft: "#243b60", green: "#83d6b4", greenBg: "#153b32", amber: "#f4cc7f", amberBg: "#3f3222",
  danger: "#ffadba", dangerBg: "#472533", navy: "#192e53",
};
function Botao({ label, icon, onPress, disabled = false, primary = false, small = false, tema, testID }: {
  label: string; icon?: IoniconName; onPress: () => void; disabled?: boolean; primary?: boolean; small?: boolean; tema: Tema; testID?: string;
}) {
  return <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled }}
    testID={testID} disabled={disabled} onPress={onPress}
    style={({ pressed }) => [s.button, small && s.buttonSmall, {
      borderColor: primary ? "#225fe5" : tema.line, backgroundColor: primary ? "#225fe5" : tema.surface,
      opacity: disabled ? 0.42 : pressed ? 0.78 : 1,
    }]}>
    {icon && <Ionicons name={icon} size={small ? 16 : 18} color={primary ? "#ffffff" : tema.accent} />}
    <Text style={[s.buttonText, { color: primary ? "#ffffff" : tema.ink }]}>{label}</Text>
  </Pressable>;
}
function Caixa({ checked, onPress, label, disabled = false, tema, texto, testID }: {
  checked: boolean | "mixed"; onPress: () => void; label: string; disabled?: boolean; tema: Tema; texto?: string; testID?: string;
}) {
  return <Pressable accessibilityRole="checkbox" accessibilityLabel={label} accessibilityState={{ checked, disabled }}
    disabled={disabled} onPress={onPress} testID={testID} style={[s.checkTouch, { opacity: disabled ? .45 : 1 }]}>
    <View style={[s.checkbox, { borderColor: checked ? "#225fe5" : tema.muted, backgroundColor: checked ? "#225fe5" : tema.surface }]}>
      {!!checked && <Ionicons name={checked === "mixed" ? "remove" : "checkmark"} size={15} color="#fff" />}
    </View>
    {texto && <Text style={[s.body, { color: tema.ink, flexShrink: 1 }]}>{texto}</Text>}
  </Pressable>;
}
function Seletor({ label, value, options, onChange, disabled, tema, pesquisavel = true }: {
  label: string; value: string; options: Opcao[]; onChange: (value: string) => void; disabled: boolean; tema: Tema; pesquisavel?: boolean;
}) {
  const [aberto, setAberto] = useState(false);
  const [busca, setBusca] = useState("");
  const atual = options.find(o => o.value === value);
  const encontrados = useMemo(() => options.filter(o => textoBusca(`${o.label} ${o.detalhe || ""}`).includes(textoBusca(busca))), [options, busca]);
  const fechar = () => { setAberto(false); setBusca(""); };
  return <View style={s.field}>
    <Text style={[s.fieldLabel, { color: tema.muted }]}>{label}</Text>
    <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled, expanded: aberto }}
      disabled={disabled} onPress={() => setAberto(true)} style={[s.select, { borderColor: tema.line, backgroundColor: tema.surface, opacity: disabled ? .5 : 1 }]}>
      <Text style={[s.body, { color: tema.ink, flex: 1 }]} numberOfLines={1}>{atual?.label || "Escolher…"}</Text>
      <Ionicons name="chevron-down" size={16} color={tema.muted} />
    </Pressable>
    <Modal visible={aberto} transparent animationType="fade" onRequestClose={fechar}>
      <View style={s.overlay}>
        <View style={[s.modal, { backgroundColor: tema.surface, borderColor: tema.line }]} accessibilityViewIsModal>
          <View style={s.between}>
            <Text accessibilityRole="header" style={[s.sectionTitle, { color: tema.ink }]}>{label}</Text>
            <Botao label="Fechar" icon="close" onPress={fechar} small tema={tema} />
          </View>
          {pesquisavel && <TextInput accessibilityLabel={`Pesquisar ${label.toLowerCase()}`} placeholder="Digite para encontrar…"
            placeholderTextColor={tema.muted} value={busca} onChangeText={setBusca} autoFocus={Platform.OS === "web"}
            style={[s.input, { color: tema.ink, borderColor: tema.line, marginTop: 14 }]} />}
          <FlatList style={{ maxHeight: 350, marginTop: 12 }} data={encontrados} keyExtractor={item => item.value || "__todos"}
            keyboardShouldPersistTaps="handled" ListEmptyComponent={<Text style={[s.body, { color: tema.muted, padding: 20 }]}>Nenhuma opção encontrada.</Text>}
            renderItem={({ item }) => <Pressable accessibilityRole="radio" accessibilityState={{ checked: value === item.value }}
              accessibilityLabel={`${item.label}${item.detalhe ? `, ${item.detalhe}` : ""}`} disabled={disabled}
              onPress={() => { onChange(item.value); fechar(); }}
              style={[s.option, { backgroundColor: value === item.value ? tema.soft : tema.surface, borderColor: tema.line }]}>
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={[s.body, { color: tema.ink, fontWeight: "600" }]}>{item.label}</Text>
                {item.detalhe && <Text style={[s.caption, { color: tema.muted }]}>{item.detalhe}</Text>}
              </View>
              {typeof item.count === "number" && <Text style={[s.count, { color: tema.accent, backgroundColor: tema.soft }]}>{item.count}</Text>}
              {value === item.value && <Ionicons name="checkmark-circle" size={20} color={tema.accent} />}
            </Pressable>} />
        </View>
      </View>
    </Modal>
  </View>;
}
function Status({ enviado, tema }: { enviado: boolean; tema: Tema }) {
  return <View style={[s.badge, { backgroundColor: enviado ? tema.greenBg : tema.amberBg }]}>
    <Ionicons name={enviado ? "checkmark-circle-outline" : "time-outline"} size={14} color={enviado ? tema.green : tema.amber} />
    <Text style={[s.caption, { color: enviado ? tema.green : tema.amber, fontWeight: "600" }]}>{enviado ? "Enviado" : "Não enviado"}</Text>
  </View>;
}
function Linha({ c, checked, busy, ativo, tema, compacto, onCheck, onUser, onActivity, onSend, onDownload }: {
  c: Certificado; checked: boolean; busy: boolean; ativo: boolean; tema: Tema; compacto: boolean;
  onCheck: () => void; onUser: () => void; onActivity: () => void; onSend: () => void; onDownload: () => void;
}) {
  const nome = <Pressable accessibilityRole="button" accessibilityLabel={`Ver certificados de ${c.participante || "participante"}, ${c.email}, certificado ${c.cod_certificado}`}
    onPress={onUser} disabled={busy}>
    <Text style={[s.body, { color: tema.ink, fontWeight: "600" }]}>{c.participante || "Participante não informado"}</Text>
    <Text selectable style={[s.caption, { color: emailValido(c.email) ? tema.muted : tema.danger, marginTop: 5 }]}>{c.email || "Sem e-mail cadastrado"}</Text>
  </Pressable>;
  const atividade = <Pressable accessibilityRole="button" accessibilityLabel={`Ver atividade ${c.atividade || "não informada"}, certificado ${c.cod_certificado}`}
    onPress={onActivity} disabled={busy}>
    <Text style={[s.body, { color: tema.accent }]}>{c.atividade || "Atividade não informada"}</Text>
    <Text style={[s.caption, { color: tema.muted, marginTop: 4 }]}>Certificado #{c.cod_certificado}</Text>
  </Pressable>;
  const acoes = <View style={s.row}>
    <Botao tema={tema} small icon="mail-outline" label={c.status === 1 ? "Reenviar" : "Enviar"} onPress={onSend} disabled={busy || !emailValido(c.email)} testID={`enviar-${c.cod_certificado}`} />
    <Botao tema={tema} small icon="download-outline" label="PDF" onPress={onDownload} disabled={busy} testID={`baixar-${c.cod_certificado}`} />
  </View>;
  const check = <Caixa tema={tema} checked={checked} onPress={onCheck} disabled={busy}
    label={`Selecionar certificado ${c.cod_certificado} de ${c.participante}`} testID={`check-${c.cod_certificado}`} />;
  return <View testID={`certificado-${c.cod_certificado}`} style={[s.tableRow, compacto && s.cardRow, { borderColor: tema.line, backgroundColor: checked || ativo ? tema.soft : tema.surface }]}>
    {compacto ? <>
      <View style={[s.row, { alignItems: "flex-start" }]}>{check}<View style={{ flex: 1 }}>{nome}</View>{ativo && <ActivityIndicator size="small" color={tema.accent} />}</View>
      <View style={{ paddingLeft: 36 }}>{atividade}</View>
      <View style={s.between}><Status enviado={c.status === 1} tema={tema} />{acoes}</View>
    </> : <>
      <View style={{ width: 42 }}>{check}</View>
      <View style={{ flex: 1.3, minWidth: 220, paddingRight: 20 }}>{nome}</View>
      <View style={{ flex: 1.5, minWidth: 250, paddingRight: 20 }}>{atividade}</View>
      <View style={{ width: 140 }}><Status enviado={c.status === 1} tema={tema} /></View>
      <View style={{ width: 206 }}>{ativo ? <View style={s.row}><ActivityIndicator size="small" color={tema.accent} /><Text style={[s.caption, { color: tema.muted }]}>Processando…</Text></View> : acoes}</View>
    </>}
  </View>;
}

export default function SendCerticate() {
  const tema = useColorScheme() === "dark" ? ESCURO : CLARO;
  const { width } = useWindowDimensions();
  const compacto = width < 850;
  const [eventoId, setEventoId] = useState<number | null>(null);
  const [eventoNome, setEventoNome] = useState("Evento");
  const [certificados, setCertificados] = useState<Certificado[]>([]);
  const [loadingEvento, setLoadingEvento] = useState(true);
  const [loadingCertificados, setLoadingCertificados] = useState(false);
  const [recarregar, setRecarregar] = useState(0);
  const [erroCarga, setErroCarga] = useState("");
  const [avisoDados, setAvisoDados] = useState("");
  const [filtros, setFiltros] = useState<Filtros>({ ...FILTROS_INICIAIS });
  const [ordem, setOrdem] = useState<Ordem>({ campo: "nome", direcao: "asc" });
  const [selecionados, setSelecionados] = useState<Set<number>>(new Set());
  const [pagina, setPagina] = useState(0);
  const [confirmacao, setConfirmacao] = useState<Confirmacao | null>(null);
  const [incluirEnviados, setIncluirEnviados] = useState(false);
  const [ocupado, setOcupado] = useState(false);
  const [parando, setParando] = useState(false);
  const [progresso, setProgresso] = useState({ total: 0, concluidos: 0, atual: 0, nome: "" });
  const [relatorio, setRelatorio] = useState<Relatorio | null>(null);
  const [mostrarDetalhes, setMostrarDetalhes] = useState(false);
  const [aviso, setAviso] = useState("");
  const emOperacao = useRef(false); // trava síncrona: cliques rápidos não iniciam dois lotes
  const interromper = useRef(false);
  const montado = useRef(false);

  const participantes = useMemo(() => opcoesAgrupadas(certificados, "participante"), [certificados]);
  const atividades = useMemo(() => opcoesAgrupadas(certificados, "atividade"), [certificados]);
  const filtrados = useMemo(() => filtrarOrdenar(certificados, filtros, ordem), [certificados, filtros, ordem]);
  const marcados = useMemo(() => alvoSelecionado(filtrados, selecionados), [filtrados, selecionados]);
  const plano = useMemo(() => confirmacao ? planejarOperacao(confirmacao.itens, confirmacao.modo, incluirEnviados) : null, [confirmacao, incluirEnviados]);
  const paginas = Math.max(1, Math.ceil(filtrados.length / PAGE_SIZE));
  const paginaAtual = Math.min(pagina, paginas - 1);
  const visiveis = filtrados.slice(paginaAtual * PAGE_SIZE, (paginaAtual + 1) * PAGE_SIZE);
  const bloqueado = ocupado || !!confirmacao || loadingEvento || loadingCertificados || !!erroCarga || !eventoId;
  const temFiltros = filtros.busca.trim() !== "" || !!filtros.participante || !!filtros.atividade || filtros.status !== "todos";
  const todosMarcados = filtrados.length > 0 && marcados.length === filtrados.length;
  const qtdPendentes = certificados.filter(c => c.status !== 1).length;
  const participanteAtual = participantes.find(o => o.value === filtros.participante);
  const atividadeAtual = atividades.find(o => o.value === filtros.atividade);
  const resumoFiltro = [participanteAtual ? `Participante: ${participanteAtual.label}` : "",
  atividadeAtual ? `Atividade: ${atividadeAtual.label}` : "", filtros.status === "todos" ? "" : `Status: ${filtros.status}`,
  filtros.busca.trim() ? `Busca: “${filtros.busca.trim()}”` : ""].filter(Boolean).join(" · ") || "Todos os certificados deste evento";

  useEffect(() => {
    montado.current = true;
    interromper.current = false;
    return () => { montado.current = false; interromper.current = true; };
  }, []);
  useEffect(() => {
    let alive = true;
    const controller = new AbortController();
    setLoadingEvento(true); setErroCarga("");
    (async () => {
      try {
        const userId = await AsyncStorage.getItem("userId");
        const lastId = idPositivo(await getLastEventoId(userId));
        if (!alive) return;
        if (!lastId) { setEventoNome("Nenhum evento selecionado"); setEventoId(null); return; }
        const nomeSalvo = await getLastEventoNome(userId);
        if (!alive) return;
        setEventoId(lastId);
        if (nomeSalvo) setEventoNome(String(nomeSalvo));
        try {
          const info = await postJsonSafe(`${API_BASE}/page-org.php`, { evento_id: lastId }, controller.signal);
          if (alive && ehRegistro(info) && info.evento_nome) setEventoNome(String(info.evento_nome));
        } catch { /* O nome salvo continua válido; a listagem é carregada independentemente. */ }
      } catch (erro) {
        if (alive) setErroCarga(erro instanceof Error ? erro.message : "Falha ao identificar o evento.");
      } finally { if (alive) setLoadingEvento(false); }
    })();
    return () => { alive = false; controller.abort(); };
  }, []);
  useEffect(() => {
    if (!eventoId) return;
    let alive = true;
    const controller = new AbortController();
    setLoadingCertificados(true); setErroCarga(""); setSelecionados(new Set());
    setConfirmacao(null); setPagina(0);
    postJsonSafe(`${API_BASE}/get_certificado.php?t=${Date.now()}`, { evento_id: eventoId, sincronizar: false }, controller.signal)
      .then(data => {
        if (!alive) return;
        const { lista, descartados } = normalizarCertificados(data);
        setCertificados(lista);
        setAvisoDados(descartados ? `${descartados} registro(s) duplicado(s) ou sem código válido foram desconsiderados. Revise a listagem da API.` : "");
      })
      .catch(erro => { if (alive) { setCertificados([]); setErroCarga(erro instanceof Error ? erro.message : "Falha ao carregar certificados."); } })
      .finally(() => { if (alive) setLoadingCertificados(false); });
    return () => { alive = false; controller.abort(); };
  }, [eventoId, recarregar]);
  useEffect(() => {
    if (!ocupado || Platform.OS !== "web" || typeof window === "undefined") return;
    const proteger = (e: BeforeUnloadEvent) => { e.preventDefault(); e.returnValue = ""; };
    window.addEventListener("beforeunload", proteger);
    return () => window.removeEventListener("beforeunload", proteger);
  }, [ocupado]);

  function mudarFiltros(proximos: Filtros) {
    if (bloqueado || emOperacao.current) return;
    setFiltros(proximos); setSelecionados(new Set()); setPagina(0); setAviso("");
  }
  function alternarUm(id: number) {
    if (bloqueado || emOperacao.current) return;
    setSelecionados(prev => { const next = new Set(prev); next.has(id) ? next.delete(id) : next.add(id); return next; });
  }
  function alternarTodos() {
    if (bloqueado || emOperacao.current || !filtrados.length) return;
    setSelecionados(todosMarcados ? new Set() : new Set(filtrados.map(c => c.cod_certificado)));
  }
  function abrirOperacao(modo: Modo, escopo: Escopo, individual?: Certificado) {
    if (bloqueado || emOperacao.current) return;
    if (modo === "download" && Platform.OS !== "web") { setAviso("O download de PDFs está disponível nesta tela pelo navegador."); return; }
    const itens = escopo === "individual" ? (individual ? [individual] : []) : escopo === "selecionados" ? marcados : filtrados;
    if (!itens.length) return;
    setIncluirEnviados(escopo === "individual" && individual?.status === 1);
    setConfirmacao({
      modo, escopo, itens: itens.map(c => ({ ...c })),
      descricao: escopo === "individual" ? `Certificado #${itens[0].cod_certificado} · ${itens[0].participante}` : `${escopo === "selecionados" ? "Seleção manual" : "Resultado dos filtros"} · ${resumoFiltro}`
    });
    setAviso("");
  }
  async function confirmarOperacao() {
    if (emOperacao.current || !confirmacao || !plano?.enviar.length) return;
    emOperacao.current = true;
    interromper.current = false;
    const atual = confirmacao;
    const itens = plano.enviar.map(c => ({ ...c }));
    setConfirmacao(null); setOcupado(true); setParando(false); setRelatorio(null); setMostrarDetalhes(false);
    setProgresso({ total: itens.length, concluidos: 0, atual: 0, nome: "" });
    try {
      const resultado = await executarFila(itens, async c => {
        if (atual.modo === "download") { await baixarCertificado(c); return; }
        const data = await postJsonSafe(`${API_BASE}/enviar_certificado.php`, { cod_certificado: c.cod_certificado, modo: "email" }, undefined, true);
        if (!ehRegistro(data) || !sucessoApi(data.success)) {
          throw new ErroApi(mensagemResposta(data, "O servidor não confirmou o envio."));
        }
        if (montado.current) setCertificados(prev => prev.map(item => item.cod_certificado === c.cod_certificado ? { ...item, status: 1 } : item));
      }, {
        deveParar: () => interromper.current || !montado.current,
        aoIniciar: (c) => { if (montado.current) setProgresso(prev => ({ ...prev, atual: c.cod_certificado, nome: `${c.participante} · ${c.atividade}` })); },
        aoConcluir: item => {
          if (!montado.current) return;
          setProgresso(prev => ({ ...prev, concluidos: prev.concluidos + 1 }));
          if (item.ok) setSelecionados(prev => { const next = new Set(prev); next.delete(item.certificado.cod_certificado); return next; });
        },
      });
      if (montado.current) setRelatorio({ ...resultado, modo: atual.modo, descricao: atual.descricao });
    } catch (erro) {
      if (montado.current) setAviso(erro instanceof Error ? erro.message : "Falha ao processar a operação.");
    } finally {
      emOperacao.current = false;
      if (montado.current) { setOcupado(false); setParando(false); setProgresso(prev => ({ ...prev, atual: 0 })); }
    }
  }
  const colunaOrdem = (campo: Ordem["campo"], label: string) => <Pressable accessibilityRole="button" accessibilityLabel={`Ordenar por ${label.toLowerCase()}`}
    disabled={bloqueado} onPress={() => { setOrdem({ campo, direcao: ordem.campo === campo && ordem.direcao === "asc" ? "desc" : "asc" }); setPagina(0); }} style={s.row}>
    <Text style={[s.tableHeading, { color: tema.muted }]}>{label}</Text>
    <Ionicons name={ordem.campo === campo ? (ordem.direcao === "asc" ? "arrow-up" : "arrow-down") : "swap-vertical"} size={13} color={ordem.campo === campo ? tema.accent : tema.muted} />
  </Pressable>;
  const renderLinha = (c: Certificado) => <Linha key={c.cod_certificado} c={c} checked={selecionados.has(c.cod_certificado)} busy={bloqueado}
    ativo={ocupado && progresso.atual === c.cod_certificado} tema={tema} compacto={compacto}
    onCheck={() => alternarUm(c.cod_certificado)}
    onUser={() => mudarFiltros({ ...FILTROS_INICIAIS, participante: chaveParticipante(c) })}
    onActivity={() => mudarFiltros({ ...FILTROS_INICIAIS, atividade: chaveAtividade(c) })}
    onSend={() => abrirOperacao("email", "individual", c)} onDownload={() => abrirOperacao("download", "individual", c)} />;
  const resultadoOk = relatorio?.itens.filter(i => i.ok).length || 0;
  const resultadoIncerto = relatorio?.itens.filter(i => i.incerto).length || 0;
  const resultadoFalhas = relatorio?.itens.filter(i => !i.ok && !i.incerto).length || 0;

  return <ScrollView style={{ flex: 1, backgroundColor: tema.bg }} contentContainerStyle={{ paddingBottom: 32 }} keyboardShouldPersistTaps="handled">
    <Mainframe name={eventoNome} photoUrl="evento.png" link="www.evento.com">
      <View style={[s.page, { paddingHorizontal: compacto ? 16 : 30 }]}>
        <View style={s.between}>
          <View><Text style={[s.eyebrow, { color: tema.muted }]}>CERTIFICADOS / DISTRIBUIÇÃO</Text>
            <Text accessibilityRole="header" style={[s.title, { color: tema.ink }]}>Envio de certificados</Text>
            <Text style={[s.body, { color: tema.muted, marginTop: 7 }]}>Escolha quem recebe. Confira o lote antes de enviar.</Text>
          </View>
          <View style={s.rowWrap}>
            <Botao tema={tema} label="Criar" icon="add" disabled={ocupado} onPress={() => router.push("./page")} />
            <Botao tema={tema} label="Configurações" icon="settings-outline" disabled={ocupado} onPress={() => router.push("./settings")} />
          </View>
        </View>
        <View style={[s.stats, { backgroundColor: tema.surface, borderColor: tema.line }]}>
          {[{ label: "Neste evento", value: certificados.length, icon: "document-text-outline" },
          { label: "Não enviados", value: qtdPendentes, icon: "time-outline" },
          { label: "No filtro", value: filtrados.length, icon: "filter-outline" },
          { label: "Selecionados", value: marcados.length, icon: "checkbox-outline" }].map(item =>
            <View key={item.label} style={s.stat}><Ionicons name={item.icon as IoniconName} size={19} color={tema.accent} />
              <View><Text style={[s.statValue, { color: tema.ink }]}>{item.value}</Text><Text style={[s.caption, { color: tema.muted }]}>{item.label}</Text></View>
            </View>)}
        </View>
        <View style={[s.panel, { backgroundColor: tema.surface, borderColor: tema.line }]}>
          <View style={s.between}>
            <View style={s.row}><Ionicons name="options-outline" size={19} color={tema.accent} /><Text style={[s.sectionTitle, { color: tema.ink }]}>Filtrar e organizar</Text></View>
            <Botao tema={tema} small label="Limpar filtros" icon="close-circle-outline" disabled={bloqueado || !temFiltros} onPress={() => mudarFiltros({ ...FILTROS_INICIAIS })} />
          </View>
          <View style={[s.searchBox, { borderColor: tema.line, backgroundColor: tema.bg }]}>
            <Ionicons name="search" size={19} color={tema.muted} />
            <TextInput accessibilityLabel="Buscar certificados" placeholder="Buscar por nome, e-mail, atividade ou código" placeholderTextColor={tema.muted}
              value={filtros.busca} onChangeText={busca => mudarFiltros({ ...filtros, busca })} editable={!bloqueado}
              style={[s.searchInput, { color: tema.ink }]} />
          </View>
          <View style={s.fields}>
            <Seletor tema={tema} label="Participante" value={filtros.participante} disabled={bloqueado}
              options={[{ value: "", label: "Todos os participantes" }, ...participantes]}
              onChange={participante => mudarFiltros({ ...filtros, participante })} />
            <Seletor tema={tema} label="Atividade" value={filtros.atividade} disabled={bloqueado}
              options={[{ value: "", label: "Todas as atividades" }, ...atividades]}
              onChange={atividade => mudarFiltros({ ...filtros, atividade })} />
            <Seletor tema={tema} label="Status do envio" value={filtros.status} disabled={bloqueado} pesquisavel={false}
              options={[{ value: "todos", label: "Todos os status" }, { value: "pendentes", label: "Não enviados" }, { value: "enviados", label: "Enviados" }]}
              onChange={status => mudarFiltros({ ...filtros, status: status as Filtros["status"] })} />
            <Seletor tema={tema} label="Ordenar por" value={`${ordem.campo}:${ordem.direcao}`} disabled={bloqueado} pesquisavel={false}
              options={[{ value: "nome:asc", label: "Nome · A a Z" }, { value: "nome:desc", label: "Nome · Z a A" }, { value: "atividade:asc", label: "Atividade · A a Z" }, { value: "atividade:desc", label: "Atividade · Z a A" }]}
              onChange={value => { const [campo, direcao] = value.split(":"); setOrdem({ campo: campo as Ordem["campo"], direcao: direcao as Ordem["direcao"] }); setPagina(0); }} />
          </View>
          <Text style={[s.caption, { color: tema.muted, marginTop: 13 }]}>Os filtros se combinam. Alterar um filtro limpa a seleção; mudar a ordem ou a página mantém os marcados.</Text>
          {filtros.participante.startsWith("cadastro:") && <Text style={[s.caption, { color: tema.amber, marginTop: 8 }]}>A API não informou o ID deste usuário. O recorte usa a combinação exata de nome e e-mail.</Text>}
          {filtros.atividade.startsWith("titulo:") && <Text style={[s.caption, { color: tema.amber, marginTop: 8 }]}>A API não informou o ID desta atividade. O recorte usa o título exato; confira atividades com o mesmo nome.</Text>}
        </View>
        {(!!erroCarga || !!avisoDados || !!aviso) && <View accessibilityRole="alert" style={[s.notice, { backgroundColor: tema.amberBg, borderColor: tema.line }]}>
          <Text style={[s.body, { color: erroCarga ? tema.danger : tema.amber }]}>{[erroCarga, avisoDados, aviso].filter(Boolean).join("\n")}</Text>
        </View>}
        {ocupado && <View style={[s.panel, { backgroundColor: tema.soft, borderColor: tema.line }]} accessibilityLiveRegion="polite">
          <View style={s.between}><View style={s.row}><ActivityIndicator color={tema.accent} /><Text style={[s.sectionTitle, { color: tema.ink }]}>{parando ? "Parando após o certificado atual…" : "Processando certificados…"}</Text></View>
            <Botao tema={tema} small label="Parar lote" icon="stop-circle-outline" disabled={parando} onPress={() => { interromper.current = true; setParando(true); }} />
          </View>
          <Text style={[s.body, { color: tema.muted, marginTop: 12 }]}>{progresso.concluidos} de {progresso.total} processados · {progresso.nome}</Text>
          <View accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: progresso.total, now: progresso.concluidos }} style={[s.progressTrack, { backgroundColor: tema.line }]}>
            <View style={{ height: 6, borderRadius: 10, backgroundColor: tema.accent, width: `${progresso.total ? 100 * progressoSeguro(progresso.concluidos, progresso.total) : 0}%` }} />
          </View>
          <Text style={[s.caption, { color: tema.muted, marginTop: 8 }]}>Mantenha a tela aberta. Parar não desfaz envios concluídos nem cancela a requisição já iniciada.</Text>
        </View>}
        {relatorio && <View style={[s.panel, { backgroundColor: resultadoFalhas || resultadoIncerto ? tema.amberBg : tema.greenBg, borderColor: tema.line }]} accessibilityLiveRegion="polite">
          <View style={s.between}><Text style={[s.sectionTitle, { color: tema.ink }]}>{relatorio.interrompido ? "Lote interrompido" : "Operação concluída"}</Text>
            <Botao tema={tema} small label={mostrarDetalhes ? "Ocultar resultado" : "Ver resultado"} icon="list-outline" onPress={() => setMostrarDetalhes(!mostrarDetalhes)} />
          </View>
          <Text style={[s.body, { color: tema.ink, marginTop: 10 }]}>{resultadoOk} {relatorio.modo === "email" ? "envio(s) confirmado(s) pelo servidor" : "download(s) iniciado(s)"} · {resultadoFalhas} falha(s) · {resultadoIncerto} não confirmado(s) · {relatorio.naoProcessados} não processado(s).</Text>
          {resultadoIncerto > 0 && <Text style={[s.body, { color: tema.danger, marginTop: 9 }]}>A resposta de um envio não foi confirmada. Ele pode ter sido enviado. Confira no servidor antes de reenviar; não houve nova tentativa automática.</Text>}
          {mostrarDetalhes && <View style={{ marginTop: 14, gap: 8 }}>
            <Text style={[s.caption, { color: tema.muted }]}>{relatorio.descricao}</Text>
            <ScrollView style={{ maxHeight: 240 }} nestedScrollEnabled>{relatorio.itens.map(item => <View key={item.certificado.cod_certificado} style={[s.resultLine, { borderColor: tema.line }]}>
              <Text style={[s.body, { color: tema.ink }]}>{item.ok ? "✓" : "!"} #{item.certificado.cod_certificado} · {item.certificado.participante} · {item.certificado.atividade}</Text>
              {!!item.erro && <Text style={[s.caption, { color: tema.danger, marginTop: 4 }]}>{item.erro}</Text>}
            </View>)}</ScrollView>
          </View>}
        </View>}
        <View style={[s.panel, { padding: 0, backgroundColor: tema.surface, borderColor: tema.line }]}>
          <View style={[s.between, { padding: 18 }]}>
            <View style={{ flex: 1, minWidth: 200 }}><Text style={[s.sectionTitle, { color: tema.ink }]}>Certificados disponíveis</Text>
              <Text style={[s.caption, { color: tema.muted, marginTop: 6 }]}>{resumoFiltro}</Text>
            </View>
            <View style={s.rowWrap}>
              <Botao tema={tema} small label="Atualizar" icon="refresh-outline" disabled={ocupado || !!confirmacao || loadingCertificados || loadingEvento || !eventoId}
                onPress={() => { setSelecionados(new Set()); setRecarregar(v => v + 1); }} />
              <Botao tema={tema} label={`Baixar filtrados (${filtrados.length})`} icon="download-outline" disabled={bloqueado || !filtrados.length}
                onPress={() => abrirOperacao("download", "filtrados")} testID="baixar-filtrados" />
              <Botao tema={tema} label={`Enviar filtrados (${filtrados.length})`} icon="mail-outline" disabled={bloqueado || !filtrados.length}
                onPress={() => abrirOperacao("email", "filtrados")} testID="enviar-filtrados" />
            </View>
          </View>
          <View style={[s.selectionBar, { backgroundColor: tema.soft, borderColor: tema.line }]}>
            <Caixa tema={tema} checked={todosMarcados ? true : marcados.length ? "mixed" : false} disabled={bloqueado || !filtrados.length}
              onPress={alternarTodos} label="Selecionar todos os certificados filtrados" texto={`Marcar todos os ${filtrados.length} filtrados`} testID="selecionar-todos" />
            <View style={s.rowWrap}>
              <Text style={[s.body, { color: tema.ink, fontWeight: "600" }]}>{marcados.length} selecionado(s)</Text>
              {marcados.length > 0 && <Botao tema={tema} small label="Limpar seleção" disabled={bloqueado} onPress={() => setSelecionados(new Set())} />}
              <Botao tema={tema} label="Baixar selecionados" icon="download-outline" disabled={bloqueado || !marcados.length} onPress={() => abrirOperacao("download", "selecionados")} testID="baixar-selecionados" />
              <Botao tema={tema} primary label={`Enviar selecionados (${marcados.length})`} icon="send-outline" disabled={bloqueado || !marcados.length}
                onPress={() => abrirOperacao("email", "selecionados")} testID="enviar-selecionados" />
            </View>
          </View>
          {(loadingEvento || loadingCertificados) ? <View style={s.empty}><ActivityIndicator color={tema.accent} /><Text style={[s.body, { color: tema.muted }]}>Carregando certificados…</Text></View>
            : !eventoId ? <View style={s.empty}><Text style={[s.body, { color: tema.muted }]}>Selecione um evento para consultar seus certificados.</Text></View>
              : !filtrados.length ? <View style={s.empty}><Ionicons name="search-outline" size={30} color={tema.muted} /><Text style={[s.body, { color: tema.muted }]}>{erroCarga ? "A listagem não pôde ser carregada." : certificados.length ? "Nenhum certificado atende aos filtros." : "Nenhum certificado disponível neste evento."}</Text></View>
                : compacto ? <View>{visiveis.map(renderLinha)}</View> : <ScrollView horizontal showsHorizontalScrollIndicator>
                  <View style={{ minWidth: 1000, width: "100%", flex: 1 }}>
                    <View style={[s.tableRow, { borderColor: tema.line, backgroundColor: tema.bg }]}>
                      <View style={{ width: 42 }} />
                      <View style={{ flex: 1.3, minWidth: 220 }}>{colunaOrdem("nome", "PARTICIPANTE / E-MAIL")}</View>
                      <View style={{ flex: 1.5, minWidth: 250 }}>{colunaOrdem("atividade", "ATIVIDADE")}</View>
                      <Text style={[s.tableHeading, { width: 140, color: tema.muted }]}>STATUS</Text>
                      <Text style={[s.tableHeading, { width: 206, color: tema.muted }]}>AÇÕES</Text>
                    </View>
                    {visiveis.map(renderLinha)}
                  </View>
                </ScrollView>}
          <View style={[s.between, { padding: 16 }]}>
            <View style={{ flex: 1, minWidth: 190 }}>
              <Text style={[s.caption, { color: tema.muted }]}>{filtrados.length ? `${paginaAtual * PAGE_SIZE + 1}–${Math.min((paginaAtual + 1) * PAGE_SIZE, filtrados.length)}` : "0"} de {filtrados.length} filtrados · {certificados.length} no evento</Text>
              <Text style={[s.caption, { color: tema.muted, marginTop: 5 }]}>A seleção de todos e as ações filtradas incluem todas as páginas.</Text>
            </View>
            <View style={s.row}>
              <Botao tema={tema} small label="Anterior" icon="chevron-back" disabled={bloqueado || paginaAtual === 0} onPress={() => setPagina(paginaAtual - 1)} />
              <Text style={[s.caption, { color: tema.muted }]}>{paginaAtual + 1} / {paginas}</Text>
              <Botao tema={tema} small label="Próxima" icon="chevron-forward" disabled={bloqueado || paginaAtual >= paginas - 1} onPress={() => setPagina(paginaAtual + 1)} />
            </View>
          </View>
        </View>
        <Text style={[s.caption, { color: tema.muted, textAlign: "center", lineHeight: 19 }]}>Somente certificados retornados pela listagem do evento. Os filtros não criam certificados nem alteram inscrições ou presenças.</Text>
      </View>
    </Mainframe>
    <Modal visible={!!confirmacao} transparent animationType="fade" onRequestClose={() => { if (!emOperacao.current) setConfirmacao(null); }}>
      <View style={s.overlay}>
        {confirmacao && plano && <View accessibilityViewIsModal style={[s.modal, { backgroundColor: tema.surface, borderColor: tema.line }]}>
          <View style={s.between}>
            <Text accessibilityRole="header" style={[s.sectionTitle, { color: tema.ink }]}>{confirmacao.modo === "email" ? "Conferir envio por e-mail" : "Conferir download"}</Text>
            <Botao tema={tema} small label="Fechar" icon="close" onPress={() => setConfirmacao(null)} />
          </View>
          <ScrollView style={{ maxHeight: compacto ? 410 : 480, marginTop: 14 }} keyboardShouldPersistTaps="handled">
            <Text style={[s.caption, { color: tema.muted }]}>{eventoNome} · {confirmacao.descricao}</Text>
            <View style={[s.confirmCount, { backgroundColor: tema.soft }]}>
              <Text style={[s.statValue, { color: tema.accent }]}>{plano.enviar.length} certificado(s)</Text>
              <Text style={[s.body, { color: tema.ink }]}>{confirmacao.modo === "email" ? `${plano.destinatarios} endereço(s) de e-mail · ` : ""}{plano.atividades} atividade(s)</Text>
            </View>
            {confirmacao.modo === "email" && <>
              <Text style={[s.body, { color: tema.muted }]}>Cada certificado gera um e-mail separado para o destinatário vinculado a ele. Nenhum endereço é informado manualmente ao PHP.</Text>
              {confirmacao.itens.some(c => c.status === 1) && <Caixa tema={tema} checked={incluirEnviados} onPress={() => setIncluirEnviados(v => !v)}
                label="Incluir certificados já enviados" texto={`Incluir também os ${confirmacao.itens.filter(c => c.status === 1).length} certificado(s) já enviado(s)`} testID="incluir-enviados" />}
              {!!plano.jaEnviados.length && <Text style={[s.caption, { color: tema.amber, marginTop: 6 }]}>{plano.jaEnviados.length} já enviado(s) ficarão de fora. Marque a opção acima para reenviar.</Text>}
              {!!plano.semEmail.length && <Text style={[s.caption, { color: tema.danger, marginTop: 8 }]}>{plano.semEmail.length} certificado(s) sem e-mail válido não serão enviados: {plano.semEmail.map(c => `#${c.cod_certificado} ${c.participante}`).join("; ")}.</Text>}
            </>}
            {confirmacao.modo === "download" && <Text style={[s.body, { color: tema.muted }]}>Os PDFs serão baixados separadamente. O navegador pode pedir permissão para múltiplos downloads. Baixar não marca o certificado como enviado por e-mail.</Text>}
            <Text style={[s.fieldLabel, { color: tema.muted, marginTop: 18 }]}>CERTIFICADOS DESTA OPERAÇÃO</Text>
            {plano.enviar.length ? plano.enviar.slice(0, 100).map(c => <View key={c.cod_certificado} style={[s.resultLine, { borderColor: tema.line }]}>
              <Text style={[s.body, { color: tema.ink, fontWeight: "600" }]}>#{c.cod_certificado} · {c.participante}</Text>
              <Text style={[s.caption, { color: tema.muted, marginTop: 4 }]}>{c.atividade}{confirmacao.modo === "email" ? `\n${c.email}` : ""}</Text>
            </View>) : <Text style={[s.body, { color: tema.amber, paddingVertical: 16 }]}>Nenhum certificado elegível. Revise os e-mails ou autorize o reenvio dos já enviados.</Text>}
            {plano.enviar.length > 100 && <Text style={[s.caption, { color: tema.muted }]}>Prévia dos primeiros 100. O lote contém {plano.enviar.length} certificados no total, todos no recorte informado.</Text>}
          </ScrollView>
          <View style={[s.rowWrap, { justifyContent: "flex-end", marginTop: 18 }]}>
            <Botao tema={tema} label="Cancelar" onPress={() => setConfirmacao(null)} />
            <Botao tema={tema} primary icon={confirmacao.modo === "email" ? "send-outline" : "download-outline"}
              label={confirmacao.modo === "email" ? `Confirmar envio (${plano.enviar.length})` : `Iniciar download (${plano.enviar.length})`}
              disabled={!plano.enviar.length || ocupado} onPress={() => { void confirmarOperacao(); }} testID="confirmar-operacao" />
          </View>
        </View>}
      </View>
    </Modal>
  </ScrollView>;
}
function progressoSeguro(concluidos: number, total: number): number {
  return Math.min(1, Math.max(0, concluidos / Math.max(1, total)));
}
const s = StyleSheet.create({
  page: { width: "100%", maxWidth: 1600, alignSelf: "center", paddingTop: 22, gap: 18 },
  eyebrow: { fontSize: 10, fontWeight: "700", letterSpacing: 1.5, marginBottom: 8 },
  title: { fontSize: 27, fontWeight: "700", letterSpacing: -.6 },
  sectionTitle: { fontSize: 16, fontWeight: "700" },
  body: { fontSize: 13, lineHeight: 20 },
  caption: { fontSize: 11, lineHeight: 17 },
  between: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 12 },
  row: { flexDirection: "row", alignItems: "center", gap: 8 },
  rowWrap: { flexDirection: "row", alignItems: "center", gap: 8, flexWrap: "wrap", maxWidth: "100%", flexShrink: 1 },
  button: { minHeight: 42, borderWidth: 1, borderRadius: 9, paddingHorizontal: 13, paddingVertical: 9, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 7 },
  buttonSmall: { minHeight: 35, paddingHorizontal: 10, paddingVertical: 6 },
  buttonText: { fontSize: 12, fontWeight: "600" },
  stats: { borderRadius: 13, borderWidth: 1, flexDirection: "row", flexWrap: "wrap", paddingHorizontal: 18, paddingVertical: 4 },
  stat: { minWidth: 130, flexGrow: 1, flexBasis: 130, paddingVertical: 14, flexDirection: "row", alignItems: "center", gap: 12 },
  statValue: { fontSize: 22, fontWeight: "700", marginBottom: 3 },
  panel: { borderRadius: 13, borderWidth: 1, padding: 18, overflow: "hidden" },
  fields: { flexDirection: "row", flexWrap: "wrap", gap: 14, marginTop: 14 },
  field: { flex: 1, minWidth: 205, gap: 7 },
  fieldLabel: { fontSize: 11, fontWeight: "600", letterSpacing: .35 },
  select: { minHeight: 43, borderWidth: 1, borderRadius: 8, paddingHorizontal: 12, paddingVertical: 9, flexDirection: "row", alignItems: "center", gap: 9 },
  input: { borderWidth: 1, borderRadius: 8, minHeight: 44, paddingHorizontal: 12, paddingVertical: 10, fontSize: 13 },
  searchBox: { marginTop: 16, borderRadius: 9, borderWidth: 1, flexDirection: "row", alignItems: "center", paddingHorizontal: 12, gap: 9 },
  searchInput: { flex: 1, height: 44, fontSize: 13, minWidth: 0 },
  checkTouch: { minHeight: 36, flexDirection: "row", alignItems: "center", gap: 9, paddingVertical: 6, paddingRight: 5 },
  checkbox: { width: 19, height: 19, borderWidth: 1.5, borderRadius: 4, alignItems: "center", justifyContent: "center" },
  selectionBar: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 10, paddingHorizontal: 18, paddingVertical: 12, borderTopWidth: 1, borderBottomWidth: 1 },
  tableRow: { flexDirection: "row", alignItems: "center", paddingHorizontal: 18, paddingVertical: 15, borderBottomWidth: 1 },
  cardRow: { flexDirection: "column", alignItems: "stretch", gap: 12 },
  tableHeading: { fontSize: 10, fontWeight: "700", letterSpacing: .65 },
  badge: { alignSelf: "flex-start", flexDirection: "row", alignItems: "center", gap: 5, borderRadius: 7, paddingVertical: 5, paddingHorizontal: 8 },
  empty: { minHeight: 160, padding: 24, gap: 12, justifyContent: "center", alignItems: "center" },
  notice: { padding: 15, borderRadius: 10, borderWidth: 1 },
  overlay: { flex: 1, backgroundColor: "rgba(13, 25, 46, 0.55)", alignItems: "center", justifyContent: "center", padding: 16 },
  modal: { width: "100%", maxWidth: 640, maxHeight: "94%", borderRadius: 15, borderWidth: 1, padding: 20 },
  option: { flexDirection: "row", alignItems: "center", gap: 9, padding: 13, borderBottomWidth: 1, borderRadius: 7, marginBottom: 3 },
  count: { fontSize: 11, fontWeight: "700", paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 },
  confirmCount: { borderRadius: 10, padding: 15, marginVertical: 14, gap: 4 },
  resultLine: { paddingVertical: 10, borderBottomWidth: 1 },
  progressTrack: { height: 6, borderRadius: 10, overflow: "hidden", marginTop: 13 },
});
