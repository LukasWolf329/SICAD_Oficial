import "../../../../style/global.css";

import React, { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator, FlatList, Modal, Platform, Pressable, ScrollView,
  Text, TextInput, View, useWindowDimensions,
} from "react-native";
import { useColorScheme } from "nativewind";
import { Ionicons } from "@expo/vector-icons";
import { Mainframe } from "../../../../components/NavBar";
import { router } from "expo-router";
import { getLastEventoId, getLastEventoNome } from "@/app/utils/lastEvento";
import AsyncStorage from "@react-native-async-storage/async-storage";

/** SICAD · filtros e envio individual/lote. Estilos em Tailwind via NativeWind. */
const API_BASE = "https://sicad.linceonline.com.br/controller";
// As classes estão escritas por inteiro: o scanner do Tailwind consegue encontrá-las.
function cx(...classes: string[]): string { return classes.filter(Boolean).join(" "); }
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
type RetornoOperacao = { aviso?: string; jaEnviado?: boolean; interromper?: boolean };
type ResultadoItem = { certificado: Certificado; ok: boolean; incerto: boolean; erro?: string; aviso?: string; jaEnviado?: boolean };
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
      status: Number(item.status_envio ?? item.status ?? 0) === 1 ? 1 : 0,
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
  origem: Certificado[], executar: (certificado: Certificado) => Promise<RetornoOperacao | void>,
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
      const retorno = await executar(certificado);
      resultado = { certificado, ok: true, incerto: false, aviso: retorno?.aviso, jaEnviado: retorno?.jaEnviado };
      parar = retorno?.interromper === true;
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
  const mensagem = ehRegistro(data) && typeof data.message === "string" ? data.message : fallback;
  const ref = ehRegistro(data) && typeof data.request_id === "string" ? data.request_id : "";
  return ref ? `${mensagem} (Referência: ${ref})` : mensagem;
}
function nomeArquivo(c: Certificado): string {
  const limpar = (v: string) => textoBusca(v).replace(/[^a-z0-9._-]+/g, "_").replace(/^_+|_+$/g, "").slice(0, 70) || "certificado";
  return `${limpar(c.participante)}_${limpar(c.atividade)}_${c.cod_certificado}.pdf`;
}
/** Prefere a etapa informada pelo PHP; HTTP 500 legado continua conservador. */
function interpretarErroApi(data: unknown, status: number, envioEmail: boolean): ErroApi {
  const obj = ehRegistro(data) ? data : {};
  const incerto = envioEmail && (typeof obj.envio_incerto === "boolean" ? obj.envio_incerto : status >= 500);
  return new ErroApi(mensagemResposta(data, `O servidor retornou HTTP ${status}.`), incerto,
    incerto || obj.interromper === true || [401, 403, 429].includes(status));
}

function interpretarEnvio(data: unknown): RetornoOperacao {
  if (!ehRegistro(data) || !sucessoApi(data.success)) throw interpretarErroApi(data, 200, true);
  if (data.envio_incerto === true) throw interpretarErroApi(data, 200, true);
  const jaEnviado = data.already_sent === true || data.code === "ALREADY_SENT";
  const statusPendente = data.status_updated === false;
  return {
    jaEnviado,
    aviso: jaEnviado || statusPendente ? mensagemResposta(data, "Confira o status no servidor antes de reenviar.") : undefined,
    interromper: statusPendente || data.interromper === true,
  };
}

// FIM_LOGICA_TESTAVEL

/** Envia o token real salvo pelo login. O PHP precisa validá-lo no middleware do projeto. */
async function headersApi(accept: string): Promise<Record<string, string>> {
  const token = await AsyncStorage.getItem("userToken");
  return { Accept: accept, "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) };
}

async function postJsonSafe(url: string, body: unknown, signal?: AbortSignal, envioEmail = false): Promise<unknown> {
  const controller = new AbortController();
  const cancelar = () => controller.abort();
  if (signal?.aborted) controller.abort();
  signal?.addEventListener("abort", cancelar, { once: true });
  const timer = setTimeout(cancelar, envioEmail ? 120000 : 45000);
  try {
    const res = await fetch(url, {
      method: "POST", headers: await headersApi("application/json"),
      body: JSON.stringify(body), signal: controller.signal,
    });
    const raw = await res.text();
    const data = parseApiResponse(raw);
    if (!res.ok) {
      throw interpretarErroApi(data, res.status, envioEmail);
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
async function baixarCertificado(c: Certificado, eventoId?: number): Promise<void> {
  if (Platform.OS !== "web" || typeof document === "undefined") {
    throw new ErroApi("Abra esta tela no navegador para baixar PDFs.", false, true);
  }
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 120000);
  try {
    const res = await fetch(`${API_BASE}/enviar_certificado.php`, {
      method: "POST", headers: await headersApi("application/pdf, application/json"),
      body: JSON.stringify({ cod_certificado: c.cod_certificado, evento_id: eventoId, modo: "download" }), signal: controller.signal,
    });
    if (!res.ok || !(res.headers.get("content-type") || "").toLowerCase().includes("application/pdf")) {
      const data = parseApiResponse(await res.text());
      throw interpretarErroApi(data, res.status, false);
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
    className={cx("border-[1px] rounded-[9px] flex-row items-center justify-center gap-[7px] active:opacity-80", (small) ? "min-h-[35px]" : "min-h-[42px]", (small) ? "px-[10px]" : "px-[13px]", (small) ? "py-[6px]" : "py-[9px]", (primary) ? "border-[#225fe5]" : "border-[#e1e7f0] dark:border-[#33415a]", (primary) ? "bg-[#225fe5]" : "bg-[#ffffff] dark:bg-[#182438]", (disabled) ? "opacity-[0.42]" : "opacity-100")} >
    {icon && <Ionicons name={icon} size={small ? 16 : 18} color={primary ? "#ffffff" : tema.accent} />}
    <Text className={cx("text-[12px] font-semibold", (primary) ? "text-[#ffffff]" : "text-[#17253e] dark:text-[#edf2fc]")}>{label}</Text>
  </Pressable>;
}
function Caixa({ checked, onPress, label, disabled = false, tema, texto, testID }: {
  checked: boolean | "mixed"; onPress: () => void; label: string; disabled?: boolean; tema: Tema; texto?: string; testID?: string;
}) {
  return <Pressable accessibilityRole="checkbox" accessibilityLabel={label} accessibilityState={{ checked, disabled }}
    disabled={disabled} onPress={onPress} testID={testID} className={cx("min-h-[36px] flex-row items-center gap-[9px] py-[6px] pr-[5px]", (disabled) ? "opacity-[0.45]" : "opacity-100")}>
    <View className={cx("w-[19px] h-[19px] border-[1.5px] rounded-[4px] items-center justify-center", (checked) ? "border-[#225fe5]" : "border-[#65748b] dark:border-[#abb9cf]", (checked) ? "bg-[#225fe5]" : "bg-[#ffffff] dark:bg-[#182438]")}>
      {!!checked && <Ionicons name={checked === "mixed" ? "remove" : "checkmark"} size={15} color="#fff" />}
    </View>
    {texto && <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] shrink">{texto}</Text>}
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
  return <View className="flex-1 min-w-[205px] gap-[7px]">
    <Text className="text-[11px] font-semibold tracking-[0.35px] text-[#65748b] dark:text-[#abb9cf]">{label}</Text>
    <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled, expanded: aberto }}
      disabled={disabled} onPress={() => setAberto(true)} className={cx("min-h-[43px] border-[1px] rounded-[8px] px-[12px] py-[9px] flex-row items-center gap-[9px] border-[#e1e7f0] dark:border-[#33415a] bg-[#ffffff] dark:bg-[#182438]", (disabled) ? "opacity-[0.5]" : "opacity-100")}>
      <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] flex-1" numberOfLines={1}>{atual?.label || "Escolher…"}</Text>
      <Ionicons name="chevron-down" size={16} color={tema.muted} />
    </Pressable>
    <Modal visible={aberto} transparent animationType="fade" onRequestClose={fechar}>
      <View className="flex-1 bg-[rgba(13,25,46,0.55)] items-center justify-center p-[16px]">
        <View className="w-full max-w-[640px] max-h-[94%] rounded-[15px] border-[1px] p-[20px] bg-[#ffffff] dark:bg-[#182438] border-[#e1e7f0] dark:border-[#33415a]" accessibilityViewIsModal>
          <View className="flex-row items-center justify-between flex-wrap gap-[12px]">
            <Text accessibilityRole="header" className="text-[16px] font-bold text-[#17253e] dark:text-[#edf2fc]">{label}</Text>
            <Botao label="Fechar" icon="close" onPress={fechar} small tema={tema} />
          </View>
          {pesquisavel && <TextInput accessibilityLabel={`Pesquisar ${label.toLowerCase()}`} placeholder="Digite para encontrar…"
            placeholderTextColor={tema.muted} value={busca} onChangeText={setBusca} autoFocus={Platform.OS === "web"}
            className="border-[1px] rounded-[8px] min-h-[44px] px-[12px] py-[10px] text-[13px] text-[#17253e] dark:text-[#edf2fc] border-[#e1e7f0] dark:border-[#33415a] mt-[14px]" />}
          <FlatList className="max-h-[350px] mt-[12px]" data={encontrados} keyExtractor={item => item.value || "__todos"}
            keyboardShouldPersistTaps="handled" ListEmptyComponent={<Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf] p-[20px]">Nenhuma opção encontrada.</Text>}
            renderItem={({ item }) => <Pressable accessibilityRole="radio" accessibilityState={{ checked: value === item.value }}
              accessibilityLabel={`${item.label}${item.detalhe ? `, ${item.detalhe}` : ""}`} disabled={disabled}
              onPress={() => { onChange(item.value); fechar(); }}
              className={cx("flex-row items-center gap-[9px] p-[13px] border-b-[1px] rounded-[7px] mb-[3px] border-[#e1e7f0] dark:border-[#33415a]", (value === item.value) ? "bg-[#eef4ff] dark:bg-[#243b60]" : "bg-[#ffffff] dark:bg-[#182438]")}>
              <View className="flex-1 gap-[4px]">
                <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] font-semibold">{item.label}</Text>
                {item.detalhe && <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">{item.detalhe}</Text>}
              </View>
              {typeof item.count === "number" && <Text className="text-[11px] font-bold px-[8px] py-[4px] rounded-[6px] text-[#225fe5] dark:text-[#94b9ff] bg-[#eef4ff] dark:bg-[#243b60]">{item.count}</Text>}
              {value === item.value && <Ionicons name="checkmark-circle" size={20} color={tema.accent} />}
            </Pressable>} />
        </View>
      </View>
    </Modal>
  </View>;
}
function Status({ enviado, tema }: { enviado: boolean; tema: Tema }) {
  return <View className={cx("self-start flex-row items-center gap-[5px] rounded-[7px] py-[5px] px-[8px]", (enviado) ? "bg-[#eaf7f0] dark:bg-[#153b32]" : "bg-[#fff7e8] dark:bg-[#3f3222]")}>
    <Ionicons name={enviado ? "checkmark-circle-outline" : "time-outline"} size={14} color={enviado ? tema.green : tema.amber} />
    <Text className={cx("text-[11px] leading-[17px] font-semibold", (enviado) ? "text-[#14775e] dark:text-[#83d6b4]" : "text-[#966118] dark:text-[#f4cc7f]")}>{enviado ? "Enviado" : "Não enviado"}</Text>
  </View>;
}
function Linha({ c, checked, busy, ativo, tema, compacto, onCheck, onUser, onActivity, onSend, onDownload }: {
  c: Certificado; checked: boolean; busy: boolean; ativo: boolean; tema: Tema; compacto: boolean;
  onCheck: () => void; onUser: () => void; onActivity: () => void; onSend: () => void; onDownload: () => void;
}) {
  const nome = <Pressable accessibilityRole="button" accessibilityLabel={`Ver certificados de ${c.participante || "participante"}, ${c.email}, certificado ${c.cod_certificado}`}
    onPress={onUser} disabled={busy}>
    <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] font-semibold">{c.participante || "Participante não informado"}</Text>
    <Text selectable className={cx("text-[11px] leading-[17px] mt-[5px]", (emailValido(c.email)) ? "text-[#65748b] dark:text-[#abb9cf]" : "text-[#b12b44] dark:text-[#ffadba]")}>{c.email || "Sem e-mail cadastrado"}</Text>
  </Pressable>;
  const atividade = <Pressable accessibilityRole="button" accessibilityLabel={`Ver atividade ${c.atividade || "não informada"}, certificado ${c.cod_certificado}`}
    onPress={onActivity} disabled={busy}>
    <Text className="text-[13px] leading-[20px] text-[#225fe5] dark:text-[#94b9ff]">{c.atividade || "Atividade não informada"}</Text>
    <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf] mt-[4px]">Certificado #{c.cod_certificado}</Text>
  </Pressable>;
  const acoes = <View className="flex-row items-center gap-[8px]">
    <Botao tema={tema} small icon="mail-outline" label={c.status === 1 ? "Reenviar" : "Enviar"} onPress={onSend} disabled={busy || !emailValido(c.email)} testID={`enviar-${c.cod_certificado}`} />
    <Botao tema={tema} small icon="download-outline" label="PDF" onPress={onDownload} disabled={busy} testID={`baixar-${c.cod_certificado}`} />
  </View>;
  const check = <Caixa tema={tema} checked={checked} onPress={onCheck} disabled={busy}
    label={`Selecionar certificado ${c.cod_certificado} de ${c.participante}`} testID={`check-${c.cod_certificado}`} />;
  return <View testID={`certificado-${c.cod_certificado}`} className={cx("px-[18px] py-[15px] border-b-[1px] border-[#e1e7f0] dark:border-[#33415a]", (compacto) ? "flex-col" : "flex-row", (compacto) ? "items-stretch" : "items-center", (compacto) ? "gap-[12px]" : "", (checked || ativo) ? "bg-[#eef4ff] dark:bg-[#243b60]" : "bg-[#ffffff] dark:bg-[#182438]")}>
    {compacto ? <>
      <View className="flex-row items-start gap-[8px]">{check}<View className="flex-1">{nome}</View>{ativo && <ActivityIndicator size="small" color={tema.accent} />}</View>
      <View className="pl-[36px]">{atividade}</View>
      <View className="flex-row items-center justify-between flex-wrap gap-[12px]"><Status enviado={c.status === 1} tema={tema} />{acoes}</View>
    </> : <>
      <View className="w-[42px]">{check}</View>
      <View className="grow-[1.3] shrink basis-0 min-w-[220px] pr-[20px]">{nome}</View>
      <View className="grow-[1.5] shrink basis-0 min-w-[250px] pr-[20px]">{atividade}</View>
      <View className="w-[140px]"><Status enviado={c.status === 1} tema={tema} /></View>
      <View className="w-[206px]">{ativo ? <View className="flex-row items-center gap-[8px]"><ActivityIndicator size="small" color={tema.accent} /><Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">Processando…</Text></View> : acoes}</View>
    </>}
  </View>;
}

export default function SendCertificate() {
  const { colorScheme } = useColorScheme();
  const tema = colorScheme === "dark" ? ESCURO : CLARO;
  const { width } = useWindowDimensions();
  const compacto = width < 1100;
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
    const autorizarReenvio = incluirEnviados;
    const itens = plano.enviar.map(c => ({ ...c }));
    setConfirmacao(null); setOcupado(true); setParando(false); setRelatorio(null); setMostrarDetalhes(false);
    setProgresso({ total: itens.length, concluidos: 0, atual: 0, nome: "" });
    try {
      const resultado = await executarFila(itens, async c => {
        if (atual.modo === "download") { await baixarCertificado(c, eventoId ?? undefined); return; }
        const data = await postJsonSafe(`${API_BASE}/enviar_certificado.php`, {
          cod_certificado: c.cod_certificado, evento_id: eventoId, modo: "email", reenviar: autorizarReenvio,
        }, undefined, true);
        const retorno = interpretarEnvio(data);
        if (montado.current) setCertificados(prev => prev.map(item => item.cod_certificado === c.cod_certificado ? { ...item, status: 1 } : item));
        return retorno;
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
    disabled={bloqueado} onPress={() => { setOrdem({ campo, direcao: ordem.campo === campo && ordem.direcao === "asc" ? "desc" : "asc" }); setPagina(0); }} className="flex-row items-center gap-[8px]">
    <Text className="text-[10px] font-bold tracking-[0.65px] text-[#65748b] dark:text-[#abb9cf]">{label}</Text>
    <Ionicons name={ordem.campo === campo ? (ordem.direcao === "asc" ? "arrow-up" : "arrow-down") : "swap-vertical"} size={13} color={ordem.campo === campo ? tema.accent : tema.muted} />
  </Pressable>;
  const renderLinha = (c: Certificado) => <Linha key={c.cod_certificado} c={c} checked={selecionados.has(c.cod_certificado)} busy={bloqueado}
    ativo={ocupado && progresso.atual === c.cod_certificado} tema={tema} compacto={compacto}
    onCheck={() => alternarUm(c.cod_certificado)}
    onUser={() => mudarFiltros({ ...FILTROS_INICIAIS, participante: chaveParticipante(c) })}
    onActivity={() => mudarFiltros({ ...FILTROS_INICIAIS, atividade: chaveAtividade(c) })}
    onSend={() => abrirOperacao("email", "individual", c)} onDownload={() => abrirOperacao("download", "individual", c)} />;
  const resultadoOk = relatorio?.itens.filter(i => i.ok && !i.jaEnviado).length || 0;
  const resultadoJaEnviado = relatorio?.itens.filter(i => i.jaEnviado).length || 0;
  const resultadoAvisos = relatorio?.itens.filter(i => !!i.aviso && !i.jaEnviado).length || 0;
  const resultadoIncerto = relatorio?.itens.filter(i => i.incerto).length || 0;
  const resultadoFalhas = relatorio?.itens.filter(i => !i.ok && !i.incerto).length || 0;

  return <ScrollView className="flex-1 bg-[#f5f7fb] dark:bg-[#101827]" contentContainerClassName="pb-[32px]" keyboardShouldPersistTaps="handled">
    <Mainframe name={eventoNome} photoUrl="evento.png" link="www.evento.com">
      <View className={cx("w-full max-w-[1600px] self-center pt-[22px] gap-[18px]", (compacto) ? "px-[16px]" : "px-[30px]")}>
        <View className="flex-row items-center justify-between flex-wrap gap-[12px]">
          <View><Text className="text-[10px] font-bold tracking-[1.5px] mb-[8px] text-[#65748b] dark:text-[#abb9cf]">CERTIFICADOS / DISTRIBUIÇÃO</Text>
            <Text accessibilityRole="header" className="text-[27px] font-bold tracking-[-0.6px] text-[#17253e] dark:text-[#edf2fc]">Envio de certificados</Text>
            <Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf] mt-[7px]">Escolha quem recebe. Confira o lote antes de enviar.</Text>
          </View>
          <View className="flex-row items-center gap-[8px] flex-wrap max-w-full shrink">
            <Botao tema={tema} label="Criar" icon="add" disabled={ocupado} onPress={() => router.push("./page")} />
            <Botao tema={tema} label="Configurações" icon="settings-outline" disabled={ocupado} onPress={() => router.push("./settings")} />
          </View>
        </View>
        <View className="rounded-[13px] border-[1px] flex-row flex-wrap px-[18px] py-[4px] bg-[#ffffff] dark:bg-[#182438] border-[#e1e7f0] dark:border-[#33415a]">
          {[{ label: "Neste evento", value: certificados.length, icon: "document-text-outline" },
          { label: "Não enviados", value: qtdPendentes, icon: "time-outline" },
          { label: "No filtro", value: filtrados.length, icon: "filter-outline" },
          { label: "Selecionados", value: marcados.length, icon: "checkbox-outline" }].map(item =>
            <View key={item.label} className="min-w-[130px] grow basis-[130px] py-[14px] flex-row items-center gap-[12px]"><Ionicons name={item.icon as IoniconName} size={19} color={tema.accent} />
              <View><Text className="text-[22px] font-bold mb-[3px] text-[#17253e] dark:text-[#edf2fc]">{item.value}</Text><Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">{item.label}</Text></View>
            </View>)}
        </View>
        <View className="rounded-[13px] border-[1px] p-[18px] overflow-hidden bg-[#ffffff] dark:bg-[#182438] border-[#e1e7f0] dark:border-[#33415a]">
          <View className="flex-row items-center justify-between flex-wrap gap-[12px]">
            <View className="flex-row items-center gap-[8px]"><Ionicons name="options-outline" size={19} color={tema.accent} /><Text className="text-[16px] font-bold text-[#17253e] dark:text-[#edf2fc]">Filtrar e organizar</Text></View>
            <Botao tema={tema} small label="Limpar filtros" icon="close-circle-outline" disabled={bloqueado || !temFiltros} onPress={() => mudarFiltros({ ...FILTROS_INICIAIS })} />
          </View>
          <View className="mt-[16px] rounded-[9px] border-[1px] flex-row items-center px-[12px] gap-[9px] border-[#e1e7f0] dark:border-[#33415a] bg-[#f5f7fb] dark:bg-[#101827]">
            <Ionicons name="search" size={19} color={tema.muted} />
            <TextInput accessibilityLabel="Buscar certificados" placeholder="Buscar por nome, e-mail, atividade ou código" placeholderTextColor={tema.muted}
              value={filtros.busca} onChangeText={busca => mudarFiltros({ ...filtros, busca })} editable={!bloqueado}
              className="flex-1 h-[44px] text-[13px] min-w-[0px] text-[#17253e] dark:text-[#edf2fc]" />
          </View>
          <View className="flex-row flex-wrap gap-[14px] mt-[14px]">
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
          <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf] mt-[13px]">Os filtros se combinam. Alterar um filtro limpa a seleção; mudar a ordem ou a página mantém os marcados.</Text>
          {filtros.participante.startsWith("cadastro:") && <Text className="text-[11px] leading-[17px] text-[#966118] dark:text-[#f4cc7f] mt-[8px]">A API não informou o ID deste usuário. O recorte usa a combinação exata de nome e e-mail.</Text>}
          {filtros.atividade.startsWith("titulo:") && <Text className="text-[11px] leading-[17px] text-[#966118] dark:text-[#f4cc7f] mt-[8px]">A API não informou o ID desta atividade. O recorte usa o título exato; confira atividades com o mesmo nome.</Text>}
        </View>
        {(!!erroCarga || !!avisoDados || !!aviso) && <View accessibilityRole="alert" className="p-[15px] rounded-[10px] border-[1px] bg-[#fff7e8] dark:bg-[#3f3222] border-[#e1e7f0] dark:border-[#33415a]">
          <Text className={cx("text-[13px] leading-[20px]", (erroCarga) ? "text-[#b12b44] dark:text-[#ffadba]" : "text-[#966118] dark:text-[#f4cc7f]")}>{[erroCarga, avisoDados, aviso].filter(Boolean).join("\n")}</Text>
        </View>}
        {ocupado && <View className="rounded-[13px] border-[1px] p-[18px] overflow-hidden bg-[#eef4ff] dark:bg-[#243b60] border-[#e1e7f0] dark:border-[#33415a]" accessibilityLiveRegion="polite">
          <View className="flex-row items-center justify-between flex-wrap gap-[12px]"><View className="flex-row items-center gap-[8px]"><ActivityIndicator color={tema.accent} /><Text className="text-[16px] font-bold text-[#17253e] dark:text-[#edf2fc]">{parando ? "Parando após o certificado atual…" : "Processando certificados…"}</Text></View>
            <Botao tema={tema} small label="Parar lote" icon="stop-circle-outline" disabled={parando} onPress={() => { interromper.current = true; setParando(true); }} />
          </View>
          <Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf] mt-[12px]">{progresso.concluidos} de {progresso.total} processados · {progresso.nome}</Text>
          <View accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: progresso.total, now: progresso.concluidos }} className="h-[6px] rounded-[10px] overflow-hidden mt-[13px] bg-[#e1e7f0] dark:bg-[#33415a]">
            <View className="h-[6px] rounded-[10px] bg-[#225fe5] dark:bg-[#94b9ff]" style={{width: `${progresso.total ? 100 * progressoSeguro(progresso.concluidos, progresso.total) : 0}%`}} />
          </View>
          <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf] mt-[8px]">Mantenha a tela aberta. Parar não desfaz envios concluídos nem cancela a requisição já iniciada.</Text>
        </View>}
        {relatorio && <View className={cx("rounded-[13px] border-[1px] p-[18px] overflow-hidden border-[#e1e7f0] dark:border-[#33415a]", (resultadoFalhas || resultadoIncerto || resultadoAvisos) ? "bg-[#fff7e8] dark:bg-[#3f3222]" : "bg-[#eaf7f0] dark:bg-[#153b32]")} accessibilityLiveRegion="polite">
          <View className="flex-row items-center justify-between flex-wrap gap-[12px]"><Text className="text-[16px] font-bold text-[#17253e] dark:text-[#edf2fc]">{relatorio.interrompido ? "Lote interrompido" : "Operação concluída"}</Text>
            <Botao tema={tema} small label={mostrarDetalhes ? "Ocultar resultado" : "Ver resultado"} icon="list-outline" onPress={() => setMostrarDetalhes(!mostrarDetalhes)} />
          </View>
          <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] mt-[10px]">{resultadoOk} {relatorio.modo === "email" ? "e-mail(s) aceito(s) pelo SMTP" : "download(s) iniciado(s)"} · {resultadoJaEnviado} já enviado(s), sem repetir · {resultadoFalhas} falha(s) · {resultadoIncerto} não confirmado(s) · {relatorio.naoProcessados} não processado(s).</Text>
          {resultadoAvisos > 0 && <Text className="text-[13px] leading-[20px] text-[#b12b44] dark:text-[#ffadba] mt-[9px]">O SMTP aceitou um envio, mas houve falha ao registrar o status. O lote foi interrompido. Confira o resultado e não reenvie automaticamente.</Text>}
          {resultadoIncerto > 0 && <Text className="text-[13px] leading-[20px] text-[#b12b44] dark:text-[#ffadba] mt-[9px]">A resposta de um envio não foi confirmada. Ele pode ter sido enviado. Confira no servidor antes de reenviar; não houve nova tentativa automática.</Text>}
          {mostrarDetalhes && <View className="mt-[14px] gap-[8px]">
            <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">{relatorio.descricao}</Text>
            <ScrollView className="max-h-[240px]" nestedScrollEnabled>{relatorio.itens.map(item => <View key={item.certificado.cod_certificado} className="py-[10px] border-b-[1px] border-[#e1e7f0] dark:border-[#33415a]">
              <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc]">{item.ok ? "✓" : "!"} #{item.certificado.cod_certificado} · {item.certificado.participante} · {item.certificado.atividade}</Text>
              {!!item.aviso && <Text className="text-[11px] leading-[17px] text-[#966118] dark:text-[#f4cc7f] mt-[4px]">{item.aviso}</Text>}
              {!!item.erro && <Text className="text-[11px] leading-[17px] text-[#b12b44] dark:text-[#ffadba] mt-[4px]">{item.erro}</Text>}
            </View>)}</ScrollView>
          </View>}
        </View>}
        <View className="rounded-[13px] border-[1px] p-[0px] overflow-hidden bg-[#ffffff] dark:bg-[#182438] border-[#e1e7f0] dark:border-[#33415a]">
          <View className="flex-row items-center justify-between flex-wrap gap-[12px] p-[18px]">
            <View className="flex-1 min-w-[200px]"><Text className="text-[16px] font-bold text-[#17253e] dark:text-[#edf2fc]">Certificados disponíveis</Text>
              <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf] mt-[6px]">{resumoFiltro}</Text>
            </View>
            <View className="flex-row items-center gap-[8px] flex-wrap max-w-full shrink">
              <Botao tema={tema} small label="Atualizar" icon="refresh-outline" disabled={ocupado || !!confirmacao || loadingCertificados || loadingEvento || !eventoId}
                onPress={() => { setSelecionados(new Set()); setRecarregar(v => v + 1); }} />
              <Botao tema={tema} label={`Baixar filtrados (${filtrados.length})`} icon="download-outline" disabled={bloqueado || !filtrados.length}
                onPress={() => abrirOperacao("download", "filtrados")} testID="baixar-filtrados" />
              <Botao tema={tema} label={`Enviar filtrados (${filtrados.length})`} icon="mail-outline" disabled={bloqueado || !filtrados.length}
                onPress={() => abrirOperacao("email", "filtrados")} testID="enviar-filtrados" />
            </View>
          </View>
          <View className="flex-row items-center justify-between flex-wrap gap-[10px] px-[18px] py-[12px] border-t-[1px] border-b-[1px] bg-[#eef4ff] dark:bg-[#243b60] border-[#e1e7f0] dark:border-[#33415a]">
            <Caixa tema={tema} checked={todosMarcados ? true : marcados.length ? "mixed" : false} disabled={bloqueado || !filtrados.length}
              onPress={alternarTodos} label="Selecionar todos os certificados filtrados" texto={`Marcar todos os ${filtrados.length} filtrados`} testID="selecionar-todos" />
            <View className="flex-row items-center gap-[8px] flex-wrap max-w-full shrink">
              <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] font-semibold">{marcados.length} selecionado(s)</Text>
              {marcados.length > 0 && <Botao tema={tema} small label="Limpar seleção" disabled={bloqueado} onPress={() => setSelecionados(new Set())} />}
              <Botao tema={tema} label="Baixar selecionados" icon="download-outline" disabled={bloqueado || !marcados.length} onPress={() => abrirOperacao("download", "selecionados")} testID="baixar-selecionados" />
              <Botao tema={tema} primary label={`Enviar selecionados (${marcados.length})`} icon="send-outline" disabled={bloqueado || !marcados.length}
                onPress={() => abrirOperacao("email", "selecionados")} testID="enviar-selecionados" />
            </View>
          </View>
          {(loadingEvento || loadingCertificados) ? <View className="min-h-[160px] p-[24px] gap-[12px] justify-center items-center"><ActivityIndicator color={tema.accent} /><Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf]">Carregando certificados…</Text></View>
            : !eventoId ? <View className="min-h-[160px] p-[24px] gap-[12px] justify-center items-center"><Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf]">Selecione um evento para consultar seus certificados.</Text></View>
              : !filtrados.length ? <View className="min-h-[160px] p-[24px] gap-[12px] justify-center items-center"><Ionicons name="search-outline" size={30} color={tema.muted} /><Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf]">{erroCarga ? "A listagem não pôde ser carregada." : certificados.length ? "Nenhum certificado atende aos filtros." : "Nenhum certificado disponível neste evento."}</Text></View>
                : compacto ? <View>{visiveis.map(renderLinha)}</View> : <ScrollView horizontal showsHorizontalScrollIndicator>
                  <View className="min-w-[1000px] w-full flex-1">
                    <View className="flex-row items-center px-[18px] py-[15px] border-b-[1px] border-[#e1e7f0] dark:border-[#33415a] bg-[#f5f7fb] dark:bg-[#101827]">
                      <View className="w-[42px]" />
                      <View className="grow-[1.3] shrink basis-0 min-w-[220px]">{colunaOrdem("nome", "PARTICIPANTE / E-MAIL")}</View>
                      <View className="grow-[1.5] shrink basis-0 min-w-[250px]">{colunaOrdem("atividade", "ATIVIDADE")}</View>
                      <Text className="text-[10px] font-bold tracking-[0.65px] w-[140px] text-[#65748b] dark:text-[#abb9cf]">STATUS</Text>
                      <Text className="text-[10px] font-bold tracking-[0.65px] w-[206px] text-[#65748b] dark:text-[#abb9cf]">AÇÕES</Text>
                    </View>
                    {visiveis.map(renderLinha)}
                  </View>
                </ScrollView>}
          <View className="flex-row items-center justify-between flex-wrap gap-[12px] p-[16px]">
            <View className="flex-1 min-w-[190px]">
              <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">{filtrados.length ? `${paginaAtual * PAGE_SIZE + 1}–${Math.min((paginaAtual + 1) * PAGE_SIZE, filtrados.length)}` : "0"} de {filtrados.length} filtrados · {certificados.length} no evento</Text>
              <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf] mt-[5px]">A seleção de todos e as ações filtradas incluem todas as páginas.</Text>
            </View>
            <View className="flex-row items-center gap-[8px]">
              <Botao tema={tema} small label="Anterior" icon="chevron-back" disabled={bloqueado || paginaAtual === 0} onPress={() => setPagina(paginaAtual - 1)} />
              <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">{paginaAtual + 1} / {paginas}</Text>
              <Botao tema={tema} small label="Próxima" icon="chevron-forward" disabled={bloqueado || paginaAtual >= paginas - 1} onPress={() => setPagina(paginaAtual + 1)} />
            </View>
          </View>
        </View>
        <Text className="text-[11px] leading-[19px] text-[#65748b] dark:text-[#abb9cf] text-center">Somente certificados retornados pela listagem do evento. Os filtros não criam certificados nem alteram inscrições ou presenças.</Text>
      </View>
    </Mainframe>
    <Modal visible={!!confirmacao} transparent animationType="fade" onRequestClose={() => { if (!emOperacao.current) setConfirmacao(null); }}>
      <View className="flex-1 bg-[rgba(13,25,46,0.55)] items-center justify-center p-[16px]">
        {confirmacao && plano && <View accessibilityViewIsModal className="w-full max-w-[640px] max-h-[94%] rounded-[15px] border-[1px] p-[20px] bg-[#ffffff] dark:bg-[#182438] border-[#e1e7f0] dark:border-[#33415a]">
          <View className="flex-row items-center justify-between flex-wrap gap-[12px]">
            <Text accessibilityRole="header" className="text-[16px] font-bold text-[#17253e] dark:text-[#edf2fc]">{confirmacao.modo === "email" ? "Conferir envio por e-mail" : "Conferir download"}</Text>
            <Botao tema={tema} small label="Fechar" icon="close" onPress={() => setConfirmacao(null)} />
          </View>
          <ScrollView className={cx("mt-[14px]", (compacto) ? "max-h-[410px]" : "max-h-[480px]")} keyboardShouldPersistTaps="handled">
            <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">{eventoNome} · {confirmacao.descricao}</Text>
            <View className="rounded-[10px] p-[15px] my-[14px] gap-[4px] bg-[#eef4ff] dark:bg-[#243b60]">
              <Text className="text-[22px] font-bold mb-[3px] text-[#225fe5] dark:text-[#94b9ff]">{plano.enviar.length} certificado(s)</Text>
              <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc]">{confirmacao.modo === "email" ? `${plano.destinatarios} endereço(s) de e-mail · ` : ""}{plano.atividades} atividade(s)</Text>
            </View>
            {confirmacao.modo === "email" && <>
              <Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf]">Cada certificado gera um e-mail separado para o destinatário vinculado a ele. Nenhum endereço é informado manualmente ao PHP.</Text>
              {confirmacao.itens.some(c => c.status === 1) && <Caixa tema={tema} checked={incluirEnviados} onPress={() => setIncluirEnviados(v => !v)}
                label="Incluir certificados já enviados" texto={`Incluir também os ${confirmacao.itens.filter(c => c.status === 1).length} certificado(s) já enviado(s)`} testID="incluir-enviados" />}
              {!!plano.jaEnviados.length && <Text className="text-[11px] leading-[17px] text-[#966118] dark:text-[#f4cc7f] mt-[6px]">{plano.jaEnviados.length} já enviado(s) ficarão de fora. Marque a opção acima para reenviar.</Text>}
              {!!plano.semEmail.length && <Text className="text-[11px] leading-[17px] text-[#b12b44] dark:text-[#ffadba] mt-[8px]">{plano.semEmail.length} certificado(s) sem e-mail válido não serão enviados: {plano.semEmail.map(c => `#${c.cod_certificado} ${c.participante}`).join("; ")}.</Text>}
            </>}
            {confirmacao.modo === "download" && <Text className="text-[13px] leading-[20px] text-[#65748b] dark:text-[#abb9cf]">Os PDFs serão baixados separadamente. O navegador pode pedir permissão para múltiplos downloads. Baixar não marca o certificado como enviado por e-mail.</Text>}
            <Text className="text-[11px] font-semibold tracking-[0.35px] text-[#65748b] dark:text-[#abb9cf] mt-[18px]">CERTIFICADOS DESTA OPERAÇÃO</Text>
            {plano.enviar.length ? plano.enviar.slice(0, 100).map(c => <View key={c.cod_certificado} className="py-[10px] border-b-[1px] border-[#e1e7f0] dark:border-[#33415a]">
              <Text className="text-[13px] leading-[20px] text-[#17253e] dark:text-[#edf2fc] font-semibold">#{c.cod_certificado} · {c.participante}</Text>
              <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf] mt-[4px]">{c.atividade}{confirmacao.modo === "email" ? `\n${c.email}` : ""}</Text>
            </View>) : <Text className="text-[13px] leading-[20px] text-[#966118] dark:text-[#f4cc7f] py-[16px]">Nenhum certificado elegível. Revise os e-mails ou autorize o reenvio dos já enviados.</Text>}
            {plano.enviar.length > 100 && <Text className="text-[11px] leading-[17px] text-[#65748b] dark:text-[#abb9cf]">Prévia dos primeiros 100. O lote contém {plano.enviar.length} certificados no total, todos no recorte informado.</Text>}
          </ScrollView>
          <View className="flex-row items-center gap-[8px] flex-wrap max-w-full shrink justify-end mt-[18px]">
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

