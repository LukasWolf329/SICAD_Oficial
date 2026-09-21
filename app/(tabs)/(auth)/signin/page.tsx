import { useAuthCheck } from "@/app/useAuthCheck/useAuthCheck";
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";
import { Link, useRouter } from "expo-router";
import React from "react";
import {
  Image, KeyboardAvoidingView, Modal, Platform, Pressable,
  SafeAreaView, ScrollView, Text, TextInput, View,
} from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";
import "../../../../style/global.css";

type ApiData = Record<string, unknown>;
type Credentials = { email: string; senha: string };
type ApiReply = { data: ApiData; status: number };
type Operation = "login" | "verify" | "resend" | "forgot" | null;

const api = axios.create({
  baseURL: "https://sicad.linceonline.com.br/controller/",
  timeout: 30000,
  // Um HTTP 403 com EMAIL_NOT_VERIFIED é uma resposta de negócio,
  // não um erro de conexão. O código abaixo confere status e corpo.
  validateStatus: () => true,
});

class AppError extends Error { }

function isRecord(value: unknown): value is ApiData {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function parseApiResponse(raw: unknown): ApiData | null {
  let parsed: unknown = raw;
  if (typeof raw === "string") {
    const text = raw.trim();
    try {
      parsed = JSON.parse(text);
    } catch {
      // Compatibilidade com o parser original. Corrija warnings/echo no PHP:
      // o servidor deve emitir somente JSON, sem texto antes da resposta.
      const start = text.indexOf("{");
      if (start < 0) return null;
      try { parsed = JSON.parse(text.slice(start)); } catch { return null; }
    }
  }
  return isRecord(parsed) && typeof parsed.success === "boolean" ? parsed : null;
}

function messageOf(data: ApiData, fallback: string): string {
  return typeof data.message === "string" && data.message.trim() ? data.message : fallback;
}

function requestError(error: unknown): string {
  if (error instanceof AppError) return error.message;
  if (axios.isAxiosError(error)
    && (error.code === "ECONNABORTED" || error.code === "ETIMEDOUT")) {
    return "Não foi possível confirmar a resposta do servidor. Tente novamente.";
  }
  return "Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.";
}

async function postApi(path: string, body: Record<string, string>): Promise<ApiReply> {
  const response = await api.post<unknown>(path, body);
  const data = parseApiResponse(response.data);
  if (!data) throw new AppError("Resposta inválida do servidor.");
  return { data, status: response.status };
}

export default function Login() {
  useAuthCheck();
  const router = useRouter();
  const [email, setEmail] = React.useState("");
  const [senha, setSenha] = React.useState("");
  const senhaInputRef = React.useRef<TextInput | null>(null);

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertMessage, setAlertMessage] = React.useState("");
  const [alertTitle, setAlertTitle] = React.useState("Ocorreu um erro");
  const [alertVariant, setAlertVariant] = React.useState<"error" | "info" | "success">("error");
  const [isForgotMode, setIsForgotMode] = React.useState(false);
  const [forgotEmail, setForgotEmail] = React.useState("");

  const [needVerify, setNeedVerify] = React.useState(false);
  const [verifyEmail, setVerifyEmail] = React.useState("");
  const [verifyToken, setVerifyToken] = React.useState("");
  const [verifyMessage, setVerifyMessage] = React.useState("");
  const [verifyError, setVerifyError] = React.useState(false);
  const [resendAt, setResendAt] = React.useState(0);
  const [resendSeconds, setResendSeconds] = React.useState(0);
  const [operation, setOperation] = React.useState<Operation>(null);

  // O ref impede duas chamadas antes de o React renderizar o novo estado.
  const busyRef = React.useRef(false);
  // Usa exatamente o e-mail e a senha da tentativa que abriu a validação.
  // A senha fica somente em memória, não é gravada no AsyncStorage.
  const pendingCredentials = React.useRef<Credentials | null>(null);
  const busy = operation !== null;

  React.useEffect(() => {
    const update = () => setResendSeconds(Math.max(0, Math.ceil((resendAt - Date.now()) / 1000)));
    update();
    if (resendAt <= Date.now()) return;
    const timer = setInterval(update, 1000);
    return () => clearInterval(timer);
  }, [resendAt]);

  function startOperation(next: Exclude<Operation, null>): boolean {
    if (busyRef.current) return false;
    busyRef.current = true;
    setOperation(next);
    return true;
  }

  function endOperation() {
    busyRef.current = false;
    setOperation(null);
  }

  function showError(message: string, title = "Ocorreu um erro") {
    pendingCredentials.current = null;
    setNeedVerify(false);
    setIsForgotMode(false);
    setAlertVariant("error");
    setAlertTitle(title);
    setAlertMessage(message);
    setAlertVisible(true);
  }

  function openVerification(credentials: Credentials, data: ApiData, resetCode: boolean) {
    const changedAccount = pendingCredentials.current?.email !== credentials.email;
    pendingCredentials.current = { ...credentials };
    setVerifyEmail(credentials.email);
    // Não apaga o que foi digitado quando o servidor apenas informa o intervalo.
    if (resetCode || changedAccount || data.email_sent === true || data.send_status === "FAILED") {
      setVerifyToken("");
    }
    const seconds = typeof data.retry_after === "number" && Number.isFinite(data.retry_after)
      ? Math.min(3600, Math.max(0, Math.ceil(data.retry_after)))
      : data.email_sent === true ? 60 : 0;
    setResendAt(Date.now() + seconds * 1000);
    setResendSeconds(seconds);
    setVerifyMessage(messageOf(data, "Seu e-mail ainda não foi confirmado. Cole o código recebido."));
    setVerifyError(data.send_status === "FAILED");
    setIsForgotMode(false);
    setAlertVisible(false);
    setNeedVerify(true);
  }

  async function finishSignIn(data: ApiData) {
    const user = data.usuario;
    if (typeof data.token !== "string" || !data.token.trim() || !isRecord(user)
      || typeof user.nome !== "string"
      || !((typeof user.id === "number" && Number.isFinite(user.id))
        || (typeof user.id === "string" && user.id.trim().length > 0))) {
      throw new AppError("O servidor não retornou os dados necessários para iniciar a sessão.");
    }
    try {
      await AsyncStorage.multiSet([
        ["userToken", data.token],
        ["userName", user.nome],
        ["userId", String(user.id)],
      ]);
    } catch {
      try { await AsyncStorage.multiRemove(["userToken", "userName", "userId"]); } catch { /* Sem log de dados. */ }
      throw new AppError("Não foi possível salvar a sessão neste dispositivo. Tente entrar novamente.");
    }
    pendingCredentials.current = null;
    setSenha("");
    setVerifyToken("");
    setNeedVerify(false);
    setAlertVisible(false);
    router.replace("/(tabs)/(painel)/home/page");
  }

  async function applyLoginReply(reply: ApiReply, credentials: Credentials, resetCode: boolean) {
    const { data, status } = reply;
    // Verifica o bloqueio antes de aceitar qualquer resposta de sucesso.
    if (data.code === "EMAIL_NOT_VERIFIED") {
      openVerification(credentials, data, resetCode);
      return;
    }
    if (status >= 200 && status < 300 && data.success === true) {
      await finishSignIn(data);
      return;
    }
    showError(messageOf(data, "Não foi possível entrar. Confira suas credenciais."));
  }

  async function handleSignIn() {
    if (busyRef.current) return;
    const credentials = { email: email.trim(), senha }; // Não normaliza/altera a senha.
    if (!credentials.email || !credentials.senha) {
      showError("Por favor, preencha todos os campos.");
      return;
    }
    if (!startOperation("login")) return;
    try {
      // O login.php passa a enviar o código quando a senha está correta e o e-mail está pendente.
      const reply = await postApi("login.php", credentials);
      await applyLoginReply(reply, credentials, true);
    } catch (error: unknown) {
      showError(requestError(error));
    } finally {
      endOperation();
    }
  }

  async function handleResendVerification() {
    const credentials = pendingCredentials.current;
    if (!credentials || resendAt > Date.now() || !startOperation("resend")) return;
    try {
      // Reutiliza o mesmo endpoint autenticado por senha. Não cria um reenvio público só por e-mail.
      const reply = await postApi("login.php", credentials);
      await applyLoginReply(reply, credentials, false);
    } catch (error: unknown) {
      setVerifyError(true);
      setVerifyMessage(requestError(error) + " Não foi possível confirmar o reenvio; confira também seu e-mail.");
    } finally {
      endOperation();
    }
  }

  async function handleVerifyEmail() {
    const credentials = pendingCredentials.current;
    const token = verifyToken.trim();
    if (!credentials || !token || !startOperation("verify")) return;
    let confirmed = false;
    try {
      const { data, status } = await postApi("verify_email.php", { email: credentials.email, token });
      if (status < 200 || status >= 300 || data.success !== true) {
        setVerifyError(true);
        setVerifyMessage(messageOf(data, "Código inválido ou expirado. Solicite um novo código."));
        return;
      }
      confirmed = true;
      setVerifyError(false);
      setVerifyToken("");
      setVerifyMessage("E-mail confirmado. Iniciando sua sessão...");
      // Chama a operação de login diretamente, sem disparar o bloqueio de duplo clique.
      const reply = await postApi("login.php", credentials);
      await applyLoginReply(reply, credentials, false);
    } catch (error: unknown) {
      if (confirmed) {
        showError("Seu e-mail foi confirmado, mas não foi possível concluir o login. Entre novamente.", "E-mail confirmado");
      } else {
        setVerifyError(true);
        setVerifyMessage(requestError(error));
      }
    } finally {
      endOperation();
    }
  }

  function closeVerification() {
    if (busyRef.current) return;
    pendingCredentials.current = null;
    setNeedVerify(false);
    setVerifyToken("");
  }

  function handleForgotPassword() {
    if (busyRef.current) return;
    pendingCredentials.current = null;
    setNeedVerify(false);
    setIsForgotMode(true);
    setAlertVariant("info");
    setAlertTitle("Recuperar senha");
    setAlertMessage("Digite seu e-mail para receber o código de redefinição.");
    setForgotEmail(email.trim());
    setAlertVisible(true);
  }

  async function handleSendForgotEmail() {
    const emailToSend = forgotEmail.trim();
    if (!emailToSend) {
      setAlertVariant("error");
      setAlertTitle("E-mail obrigatório");
      setAlertMessage("Digite um e-mail válido.");
      return;
    }
    if (!startOperation("forgot")) return;
    try {
      const { data, status } = await postApi("forgot_password.php", { email: emailToSend });
      if (status >= 200 && status < 300 && data.success === true) {
        setIsForgotMode(false);
        setAlertVariant("success");
        setAlertTitle("Verifique seu e-mail");
        setAlertMessage(messageOf(data, "Se o e-mail estiver cadastrado, você receberá um código para redefinir a senha."));
        return;
      }
      setAlertVariant("error");
      setAlertTitle("Erro");
      setAlertMessage(messageOf(data, "Não foi possível enviar o e-mail."));
    } catch (error: unknown) {
      setAlertVariant("error");
      setAlertTitle("Não foi possível concluir");
      setAlertMessage(requestError(error));
    } finally {
      endOperation();
    }
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className="w-5/12">
        <Image
          source={require("../../../../assets/images/side-view-login-cadastro.png")}
          style={{ width: "100%" }}
          className="mobile:h-0 mobile:w-0 mobile:hidden"
        />
      </View>
      <View className="flex-1 items-center justify-center">
        <View>
          <Image source={require("../../../../assets/images/logo-composta.png")} className="mb-4" />
          <Text className="text-6xl dark:color-white">Acesse sua conta</Text>
          <Text className="text-2xl dark:color-white">
            Ainda não tem uma conta?{" "}
            <Link href="/(tabs)/(auth)/signup/page" className="text-2xl color-sky-500">
              clique aqui para criar uma
            </Link>
          </Text>
          <View className="flex flex-col gap-1 mt-4">
            <View>
              <Text className="text-2xl dark:color-white">E-mail</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    value={email}
                    onChangeText={setEmail}
                    editable={!busy}
                    returnKeyType="next"
                    onSubmitEditing={() => senhaInputRef.current?.focus()}
                    autoCapitalize="none"
                    autoCorrect={false}
                    keyboardType="email-address"
                    textContentType="emailAddress"
                    accessibilityLabel="E-mail para login"
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>
            <View>
              <Text className="text-2xl dark:color-white">Senha</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    ref={senhaInputRef}
                    value={senha}
                    onChangeText={setSenha}
                    editable={!busy}
                    secureTextEntry
                    returnKeyType="go"
                    onSubmitEditing={() => void handleSignIn()}
                    autoCapitalize="none"
                    autoCorrect={false}
                    textContentType="password"
                    accessibilityLabel="Senha para login"
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>
            <Pressable
              onPress={() => void handleSignIn()}
              disabled={busy}
              accessibilityRole="button"
              accessibilityLabel="Entrar"
              accessibilityState={{ disabled: busy }}
              style={{ opacity: busy ? 0.6 : 1 }}
              className="w-full h-12 rounded-xl bg-green-600 mt-4 justify-center items-center"
            >
              <Text className="text-2xl font-bold text-white">
                {operation === "login" ? "Entrando..." : "Entrar"}
              </Text>
            </Pressable>
          </View>
          <Pressable onPress={handleForgotPassword} disabled={busy}>
            <Text className="dark:color-white underline text-xl mt-4">Esqueceu sua senha?</Text>
          </Pressable>
          <Link href="/(tabs)/(auth)/reset-password/page" className="dark:color-white underline text-xl mt-2">
            Já tenho o token / redefinir senha
          </Link>
        </View>
      </View>

      {/* Validação separada do NiceAlert, cuja implementação não foi fornecida. */}
      <Modal visible={needVerify} transparent animationType="fade" onRequestClose={closeVerification}>
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : undefined}
          style={{ flex: 1 }}
        >
          <ScrollView
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={{ flexGrow: 1, justifyContent: "center", padding: 24 }}
            className="bg-black/60"
          >
            <View className="w-full max-w-lg self-center rounded-2xl bg-white dark:bg-[#1f1f1f] p-6">
              <Text className="text-2xl font-bold text-slate-900 dark:text-white">Valide seu e-mail</Text>
              <Text className="mt-2 text-slate-600 dark:text-slate-300" selectable>{verifyEmail}</Text>
              <Text
                accessibilityLiveRegion="polite"
                className={verifyError
                  ? "mt-4 text-red-600 dark:text-red-400"
                  : "mt-4 text-slate-700 dark:text-slate-200"}
              >
                {verifyMessage}
              </Text>
              <TextInput
                value={verifyToken}
                onChangeText={setVerifyToken}
                editable={!busy}
                autoCapitalize="none"
                autoCorrect={false}
                maxLength={1024}
                placeholder="Cole o código completo aqui"
                accessibilityLabel="Código de verificação do e-mail"
                returnKeyType="done"
                onSubmitEditing={() => void handleVerifyEmail()}
                className="mt-4 w-full h-12 border border-slate-500 rounded-xl px-3 text-slate-900 dark:text-white"
              />
              <Pressable
                onPress={() => void handleVerifyEmail()}
                disabled={busy || !verifyToken.trim()}
                accessibilityRole="button"
                accessibilityLabel="Validar e entrar"
                accessibilityState={{ disabled: busy || !verifyToken.trim() }}
                style={{ opacity: busy || !verifyToken.trim() ? 0.6 : 1 }}
                className="mt-4 h-12 rounded-xl bg-green-600 items-center justify-center"
              >
                <Text className="font-bold text-white">{operation === "verify" ? "Validando..." : "Validar e entrar"}</Text>
              </Pressable>
              <Pressable
                onPress={() => void handleResendVerification()}
                disabled={busy || resendSeconds > 0}
                accessibilityRole="button"
                accessibilityLabel="Reenviar código"
                accessibilityState={{ disabled: busy || resendSeconds > 0 }}
                style={{ opacity: busy || resendSeconds > 0 ? 0.6 : 1 }}
                className="mt-3 min-h-12 items-center justify-center rounded-xl border border-slate-400 px-3 py-3"
              >
                <Text className="text-slate-800 dark:text-white">
                  {operation === "resend" ? "Reenviando..."
                    : resendSeconds > 0 ? `Reenviar em ${resendSeconds}s` : "Reenviar código"}
                </Text>
              </Pressable>
              <Pressable
                onPress={closeVerification}
                disabled={busy}
                accessibilityRole="button"
                className="mt-3 min-h-12 items-center justify-center py-3"
              >
                <Text className="text-slate-600 dark:text-slate-300 underline">Voltar ao login</Text>
              </Pressable>
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </Modal>

      <NiceAlert
        visible={alertVisible}
        title={alertTitle}
        message={alertMessage}
        onClose={() => {
          if (busyRef.current) return;
          setAlertVisible(false);
          setIsForgotMode(false);
        }}
        variant={alertVariant}
        showInput={isForgotMode}
        inputPlaceholder="Digite seu e-mail"
        inputValue={forgotEmail}
        onChangeInput={setForgotEmail}
        confirmText={isForgotMode ? operation === "forgot" ? "Enviando..." : "Enviar" : undefined}
        onConfirm={isForgotMode ? handleSendForgotEmail : undefined}
        confirmDisabled={busy || (isForgotMode && !forgotEmail.trim())}
      />
    </View>
  );
}
